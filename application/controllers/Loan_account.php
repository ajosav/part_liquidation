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
        // $signedRequest= "e6d8b59f768c956c8b5b0df34f78c7b7df7e237d60f0ef52bb1c3478c4102880.eyJET01BSU4iOiJyZW5tb25leS5zYW5kYm94Lm1hbWJ1LmNvbSIsIk9CSkVDVF9JRCI6IjEwOTExMzU2IiwiQUxHT1JJVEhNIjoiaG1hY1NIQTI1NiIsIlRFTkFOVF9JRCI6InJlbm1vbmV5IiwiVVNFUl9LRVkiOiI4YTlmODdkMTc0OTE1YjYxMDE3NDkxN2MxZmE5MDAxMiJ9";
        
        $signedRequestParts = explode('.', $signedRequest);
        $mambuPostBack = json_decode(base64_decode($signedRequestParts[1]), TRUE);

        $loan_id = $mambuPostBack['OBJECT_ID'];
        // $loan_id = 10911356;
        $encoded_key = $mambuPostBack['USER_KEY'];

        // $loan_id = 30462881;


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

        $trans_id = $transaction_channel['id'];
        $gl_names = "";
        
        $trans_chan = $this->Base_model->fetch_gl_accounts();
        
        foreach($trans_chan as $channels) {
            $gls = $this->Base_model->filter_gl_accounts($channels);
            if($gls) {
                 if($gls['id'] != 'XXXXXXXXXXXX') {
                    $name = $gls['name'];
                    $id = $gls['id'];
                    $active = $gls['id'] == $trans_id ? 'selected' : '';
                    $gl_names .= "<option value='{$id}' data-id='{$id}' {$active}> {$name} </option>";
                }
            }
           
            
        }
        

        $trans_methods_url = $this->mambu_base_url . "api/customfields/Repayment_Method_Transactions";
        $repayment_response = json_decode($this->Base_model->call_mambu_api_get($trans_methods_url), TRUE);

        $methods = "";
        $repayment_methods = $repayment_response['customFieldSelectionOptions'];
        foreach($repayment_methods as $payment_methods) {
            $value = $payment_methods['value'];
            $encodedKey = $payment_methods['encodedKey'];
            $methods .= "<option value='{$value}' data-id='{$encodedKey}'> {$value} </option>";
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
        $data['transaction_method'] = $methods;
		
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
        $transaction_method = $this->input->post('transaction_method');

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
                $penalty_due = 0;
                foreach($repayments as $repayment) {
                    $fees_due += $repayment->feesDue;
                    $interest += $repayment->interestDue;
                    $penalty_due += $repayment->penaltyDue;
                   
                }
                $interest = $interest / $tenor;
                $fees_due = $fees_due / $tenor;
                $penalty_due = $penalty_due / $tenor;
                foreach($repayments as $index => $repayment) {
                    if($index < $tenor) {
                        $repayment->principalDue = $spread_principal;
                        $repayment->interestDue = $interest;
                        $repayment->feesDue = $fees_due;
                        $repayment->penaltyDue = $penalty_due;
                        $new_schedule[] = $repayment;
                    } else {
                        $repayment->principalDue = 0;
                        $repayment->interestDue = 0;
                        $repayment->feesDue = 0;
                        $repayment->penaltyDue = 0;
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
                "transaction_method" => $transaction_method,
                "tenure" => $tenor,
                "principalBalance" => abs(ceil($principal_remainder - ($principal_balance - $principal_due))),
                "date_generated" => date('Y-m-d H:i:s'),
                "outstandingBalance" => $outstanding_balance
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
                        "penaltyDue" => $schedule->penaltyDue,
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
                json_encode("Liquidation amount is not lower than total due")
            );
       
    }

    public function schedule_review($schedule_id) {
        if($loan_schedule = $this->Base_model->find("loan_schedule", ['schedule_id' => $schedule_id])) {
            $repayment_schedule = $this->Base_model->selectRepayment($loan_schedule->schedule_id);
            $data = [
                "loan_schedule" => $loan_schedule,
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
        // $personal_email = "jadebayo@renmoney.com";


        $valid_till = date("Y-m-d H:i:s", strtotime("+24 hours"));
        $link = base64_encode(json_encode(['schedule_id' => $schedule_id, "valid_till" => $valid_till, "loan_id" => $loan]));
        $link = base_url() . "client/loan_schedule/$link";

        $content = '
        <div style="font-family: verdana, Trebuchet ms, arial; line-height: 1.5em">
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
            <p style="margin-top:0;margin-bottom:0;">Dear '.$client_name.', </p>
            <p style="margin-top:0;margin-bottom:0;">Please see below the new repayment schedule based on your Part Liquidation Request<br>
            Liquidation Amount: N'.$liquidationAmount.'<br>
            Loan ID:'.$loan.'<br>
            <a href='.$link.'>View Schedule</a> <br>
            Click on the Link above to Accept or Reject.<br>
            For any enquiries, contact hello@renmoney.com</p>
            <p style="margin-top:0;margin-bottom:0;"><b>Thank you for choosing RenMoney MFB LTD.</b> </p>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
            <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
        </div>
        ';
        $email_body = [
            "recipient" => [$personal_email],
            "subject" => "Part-liquidation of your Loan Account",
            "content" => $content,
            "cc" => [],
            "category" => ['Part-liquidation']
        ];

        $this->Base_model->notifyMail($email_body);
        $this->Base_model->update_table("loan_schedule", ['loan_id' => $loan], ['status' => 1]);

        $this->load->view('part_liquidation/meta_link');
        $this->load->view('part_liquidation/email_successfully_sent'); 
        $this->load->view('part_liquidation/footer_link');
    }

    public function liquidation_history($loan_id) {
        $loan_schedule = $this->Base_model->findWhere("loan_schedule", ['loan_id' => $loan_id]);
        $data = [
            "loan_schedule" => $loan_schedule
        ];
		$this->load->view('part_liquidation/meta_link');
		$this->load->view('part_liquidation/liquidation_history', $data); 
		$this->load->view('part_liquidation/footer_link');
    }
	

}