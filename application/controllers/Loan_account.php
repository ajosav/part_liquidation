<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Loan_account extends CI_Controller {

    public $mambu_base_url;
    private $today;
	public function __construct() {
		
        parent::__construct();
        
		$this->config->load('renmoney');
		$this->load->helper(array('form', 'url'));

        $this->load->library('form_validation');
        $this->today = date('Y-m-d H:i:s');

        $this->load->model('Base_model'); 
        
        
        $this->mambu_base_url = $this->config->item('rnm_mambu_base_url');
        
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, access-control-allow-origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

    }

    //Method for app installation on MAMBU
    public function install() {
        
    }

    //Method for app uninstallation on MAMBU
    public function uninstall() {
        
    }

    public function index() {
        echo "Welcome to Part liquidation App";
    }

    /**
	* @Mambu starts from here
	* Method to get customer data from Mambu 
	* Load into the form
	* 
	*/
    public function start() {
		$signedRequest = $this->input->post('signed_request'); // Script to get cleint data from Mambu
        $signedRequest= "e6d8b59f768c956c8b5b0df34f78c7b7df7e237d60f0ef52bb1c3478c4102880.eyJET01BSU4iOiJyZW5tb25leS5zYW5kYm94Lm1hbWJ1LmNvbSIsIk9CSkVDVF9JRCI6IjEwOTExMzU2IiwiQUxHT1JJVEhNIjoiaG1hY1NIQTI1NiIsIlRFTkFOVF9JRCI6InJlbm1vbmV5IiwiVVNFUl9LRVkiOiI4YTlmODdkMTc0OTE1YjYxMDE3NDkxN2MxZmE5MDAxMiJ9";
        
        $signedRequestParts = explode('.', $signedRequest);
        $mambuPostBack = json_decode(base64_decode($signedRequestParts[1]), TRUE);

        // $loan_id = $mambuPostBack['OBJECT_ID'];
        // $loan_id = 10911356;
        $encoded_key = $mambuPostBack['USER_KEY'];
		
        $loan_id = 30462881;

		$endpointURL = $this->mambu_base_url . "api/users/" . $encoded_key;
        $user_key_response = $this->Base_model->call_mambu_api_get($endpointURL);
		$get_user_key = json_decode($user_key_response, TRUE);
		$username = $get_user_key['username'];
		

		// Fetch loan details using the active loan ID
		$endpointURL = $this->mambu_base_url . "api/loans/" . $loan_id ."?fullDetails=true";
        $response = $this->Base_model->call_mambu_api_get($endpointURL);
		
		$data = json_decode($response, TRUE);
		$account_holder_key = $data['accountHolderKey'];
		
        $endpointURL = $this->mambu_base_url . "api/clients/" . $account_holder_key ."?fullDetails=true";
        $response = $this->Base_model->call_mambu_api_get($endpointURL);
			
        $client_details = json_decode($response, TRUE);
        
		$personal_email = $client_details['client']['emailAddress'];
        $client_id = $client_details['client']['id'];
        
        $next_repayment_due = $this->get_next_repayment_due_date($loan_id);
        $next_repayment =  $next_repayment_due != null ? date('Y-m-d', strtotime($next_repayment_due->dueDate)): "OVERDUE";

        $transaction_channel = $data['disbursementDetails']['transactionDetails']['transactionChannel'];

        $trans_name = $transaction_channel['name'];
        $trans_id = $transaction_channel['id'];
        $gl_names = "";
        
        if (!$gl_accounts = $this->Base_model->fetch_gl_accounts()) {
			$gl_names = "
				<option value='{$trans_id}' data-id='{$trans_id}' selected>{$trans_name}</option>
			";

		} else {
			foreach($gl_accounts as $gls) {
				$name = $gls['name'];
                $id = $gls['id'];
                $active = $gls['id'] == $trans_id ? 'selected' : '';
				$gl_names .= "<option value='{$id}' data-id='{$id}' {$active}> {$name} </option>";
			}
        }
        
        // $this->Base_model->dd($this->Base_model->fetch_gl_accounts());
			
		$data['client_email'] = $personal_email;
        $data['username'] = $username;	
        $data['loan_id'] = $loan_id;
        $data['client_id'] = $client_id;
        $data['mambu_user_key'] = $encoded_key;
        $data['next_repayment_due_date'] = $next_repayment;
        $data['max_tenor'] = $this->get_max_available_tenor($loan_id);
        $data['outstanding_repayments'] = count($this->get_outstanding_repayments($loan_id));
        $data['transaction_channel'] = $gl_names;
		
		$this->load->view('part_liquidation/meta_link');
		$this->load->view('part_liquidation/account_details', $data); 
		$this->load->view('part_liquidation/footer_link');
		   
    }

    private function get_next_repayment_due_date($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id));

        foreach($repayments as $index => $repayment) {
            if($repayment->dueDate > $this->today) {
                return $repayments[$index];
            } 
        }

        return null;
    }

    private function get_outstanding_repayments($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id));
        $outstanding_repayments = [];
        foreach($repayments as $repayment) {
            if((string) $repayment->state != "PAID") {
                $outstanding_repayments[] = $repayment;
            }
        }

        return $outstanding_repayments;
    }

    private function get_max_available_tenor($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id));
        $available_tenor = [];
        foreach($repayments as $repayment) {
            if((string) $repayment->state != "PAID" && (string) $repayment->state != "LATE") {
                $available_tenor[] = $repayment;
            }
        }

        return $available_tenor;
    }

    public function recalculate_schedule() {
        if($this->input->method() != 'post') {
            $data = [
                'heading' => "Method not allowed",
                'message' => "Post Methods only are allowed"
            ];
            $this->load->view('errors/html/error_general', $data);
        }

        $this->form_validation->set_rules('repayment_tenor', 'Repayment Tenor', 'trim|required',
            array('required' => 'Please enter a new repayment tenor ')
        );
        $this->form_validation->set_rules('part_liq_amount', 'Liquidation Amount ', 'trim|required',
            array('required' => 'Please enter a liquidation amount')
        );
        $tenor = $this->input->post('repayment_tenor');
        $max_tenor = $this->input->post('max_tenor');
        $liquidation_amount = $this->input->post('part_liq_amount');

        if ($tenor < $max_tenor )
        {
            $this->form_validation->set_rules('repayment_tenor', 'Repayment Tenor', "trim|required",
                array('max' => 'Tenor cannot exceed maximum tenor of existing loan')
            );
        }

        if ($this->form_validation->run() == FALSE)
        {
            return $this->output
				->set_content_type('application/json')
				// ->set_status_header(400)
				->set_output(
					json_encode(validation_errors())
				);
        }

        // 50k - (inte due 21k+18k)- (penalty=764+74)- (Int Accr/Fee 5590+ 90) - Principal bala (250k)


        $loan_id = $this->input->post('loan_id');
        $interest_rate = $this->input->post('interest_rate');
        $interest_accrued = $this->input->post('interest_accrued');
        $interest_overdue = $this->input->post('interest_overdue');
        $principal_balance = $this->input->post('principal_balance');
        $principal_due = $this->input->post('principal_due');
        $penalty_due = $this->input->post('penalty_due');
        $accountHolderKey = $this->input->post('accountHolderKey');
        $fees_due = $this->input->post('fees_due');
        $payment_status = $this->input->post('payment_status');
        $transaction_date = $this->input->post('transaction_date');
        $transction_channel = $this->input->post('transaction_channel');

        $principal_remainder = $liquidation_amount - ($interest_overdue) - ($penalty_due) - ($interest_accrued + $fees_due) - ($principal_due);
        $outstanding_balance = ($interest_overdue) + ($penalty_due) + ($interest_accrued + $fees_due) + ($principal_due);

        // pass the outstanding balance to repayments endpoint to cover for debts

        // create a new repayment schedule
        $repayments = $this->get_max_available_tenor($loan_id);
        if($principal_remainder > 0) {
            $new_principal_bal =  abs(ceil($principal_remainder - ($principal_balance - $principal_due)));
            $spread_principal = ($new_principal_bal / $tenor);
            $new_schedule = [];
            if($tenor == $max_tenor) {
                foreach($repayments as $repayment) {
                    $repayment->principalDue = $spread_principal;
                    $new_schedule[] = $repayment;
                }
            } else {
                $interest  = 0;
                $fees_due = 0;
                foreach($repayments as $repayment) {
                    $fees_due += $repayment->feesDue;
                    $interest += $repayment->interestDue;
                   
                }
                $interest = $interest / $tenor;
                $fees_due = $fees_due / $tenor;
                foreach($repayments as $index => $repayment) {
                    if($index < $tenor) {
                        $repayment->principalDue = $spread_principal;
                        $repayment->interestDue = $interest;
                        $repayment->feesDue = $fees_due;
                        $new_schedule[] = $repayment;
                    } else {
                        $repayment->principalDue = 0;
                        $repayment->interestDue = 0;
                        $repayment->feesDue = 0;
                        $new_schedule[] = $repayment;
                    }
                    
                }

            }

            $schedule_id = uniqid('sch');
            $loan_schedule = [
                "loan_id" => $loan_id,
                "accountHolderKey" => $accountHolderKey,
                "schedule_id" => $schedule_id,
                "liquidationAmount" => $liquidation_amount,
                "paymentStatus" => $payment_status,
                "transactionDate" => $transaction_date,
                "transactionChannel" => $transction_channel,
                "tenure" => $tenor,
                "principalBalance" => abs(ceil($principal_remainder - ($principal_balance - $principal_due))),

            ];
            
            $schedule_data = [];

            if($this->Base_model->create('loan_schedule', $loan_schedule)) {
                foreach($new_schedule as $schedule) {
                    $data = [
                        "schedule_id" => $schedule_id,
                        "encodedKey" => $schedule->encodedKey,
                        "interestDue" => $schedule->interestDue,
                        "principalDue" => $schedule->principalDue,
                        "dueDate" => $schedule->dueDate,
                        "penaltyDue" => $schedule->dueDate,
                        "feesDue" => $schedule->feesDue,
                        "parentAccountKey" => $schedule->parentAccountKey
                    ];
                    array_push($schedule_data, $data);
                }
               if($this->Base_model->createBatch('repayment_schedule', $schedule_data)) {
                    return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(200)
                    ->set_output(
                        json_encode(["status" => "created", "message" => "Schedule Successfully Re-calculated", "schedule_id" => $schedule_id])
                    );
               }
            }


            return $this->output
            ->set_content_type('application/json')
            ->set_status_header(400)
            ->set_output(
                json_encode("Error Creating Generating Schedule")
            );

            

        }

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(400)
            ->set_output(
                json_encode("Liquidation amount is not lower than fees due")
            );
       
    }

    public function schedule_review($schedule_id) {
        if($loan_schedule = $this->Base_model->find("loan_Schedule", ['schedule_id' => $schedule_id])) {
            $repayment_schedule = $this->Base_model->selectRepayment($loan_schedule->schedule_id);
            $data = [
                "loan_Schedule" => $loan_schedule,
                "repayment_schedule" => $repayment_schedule
            ];
            
            

            $this->load->view('part_liquidation/meta_link');
            $this->load->view('part_liquidation/preview_schedule_view', $data); 
            $this->load->view('part_liquidation/footer_link');
        } else {
            redirect(base_url().'Loan_account/start');
        }
    }


    public function send_schedule_to_customer() {
        $client_key = $this->input->post('accountHolderKey');
        $loan = $this->input->post('loan_id');
        $schedule_id = $this->input->post('schedule_id');
        $liquidationAmount = number_format($this->input->post('liquidationAmount'));
        $endpointURL = $this->mambu_base_url . "api/clients/" . $client_key ."?fullDetails=true";
        $response = $this->Base_model->call_mambu_api_get($endpointURL);
			
        $client_details = json_decode($response, TRUE);
        
        $personal_email = $client_details['client']['emailAddress'];
        $client_name = $client_details['client']['firstName'];
        $link = base_url() . "/view_schedule";

        $this->Base_model->notifyMail(["{$personal_email}"], "Part-liquidation of your Loan Account", "
            <p> <img src = 'https://www.renmoneyng.com/images/uploads/email-template-top.png' alt = '' /> </p>
            <p>Dear {$client_name}, </p>
            <p>Please find attached the New repayment schedule based on your recent bulk payment N{$liquidationAmount} for your loan with ID {$loan} <br>
            {$link} <br>
            Click on the Link above to Accept or Reject.
            For any enquiries, contact hello@renmoney.com</p>
            <p>Thank you for choosing RenMoney MFB LTD. 
            <br> <p>  <img src = 'https://www.renmoneyng.com/images/uploads/email-template-bottom.png ' alt = '' /> </p>
        ");
        
        $this->load->view('part_liquidation/meta_link');
        $this->load->view('part_liquidation/email_successfully_sent'); 
        $this->load->view('part_liquidation/footer_link');
    }
	

}