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
        // $loan_id = 16333848;
        $encoded_key = $mambuPostBack['USER_KEY'];

        // $loan_id = 30380091;


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
        
        foreach($trans_chan as $gls) {
            // $gls = $this->Base_model->filter_gl_accounts($channels);
            if($gls['id'] != 'XXXXXXXXXXXX') {
                $customFields = $gls['customFields'];
                $data_id = "";
                foreach($customFields as $fields) {
                    if($fields['id'] == "Repayment_Method_Transactions") {
                        $data_id = $fields['id'];
                    }
                }
                $name = $gls['name'];
                $id = $gls['id'];
                $active = $gls['id'] == $trans_id ? 'selected' : '';
                $gl_names .= "<option value='{$id}' data-id='{$data_id}' {$active}> {$name} </option>";
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
        $data['max_tenor'] = $this->get_all_future_repayments($loan_id);
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
            if((string) $repayment->state != "PAID" && $repayment->state != "GRACE") {
                $outstanding_repayments[] = $repayment;
            }
        }

        return $outstanding_repayments;
    }

    private function get_max_available_tenor($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id));
        $available_tenor = [];
        foreach($repayments as $repayment) {
            if((string) $repayment->state != "PAID" && (string) $repayment->state != "LATE" && (string) $repayment->state != "GRACE") {
                $available_tenor[] = $repayment;
            }
        }

        return $available_tenor;
    }

    private function get_late_repayments($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id));
        $available_tenor = [];
        foreach($repayments as $repayment) {
            if((string) $repayment->state == "LATE") {
                $available_tenor[] = $repayment;
            }
        }

        return $available_tenor;
    }
    private function get_all_late_repayments($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id));
        $available_tenor = [];
        foreach($repayments as $repayment) {
            $due_date = date('Y-m-d', strtotime($repayment->dueDate));
            $today = date('Y-m-d');
            if((string) $repayment->state == "LATE" || $due_date < $today) {
                $available_tenor[] = $repayment;
            }
        }

        return $available_tenor;
    }

    private function get_all_future_repayments($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id));
        $available_tenor = [];
        foreach($repayments as $repayment) {
            $due_date = date('Y-m-d', strtotime($repayment->dueDate));
            $today = date('Y-m-d');
            //  2020-10-15
            // 2020-10-12
            if((string) $repayment->state != "PAID" && (string) $repayment->state != "LATE" && $due_date > $today && (string) $repayment->state != "GRACE") {
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

        $liquidation_amount = (float) trim(str_replace(',','', preg_replace('/\s+/', '', $liquidation_amount)));
        $loan_id = $this->input->post('loan_id');
        $interest_rate = $this->input->post('interest_rate');
        $interest_accrued = $this->input->post('interest_accrued');
        $interest_overdue = $this->input->post('interest_overdue');
        $interest_balance = $this->input->post('interestBalance');
        $principal_balance = $this->input->post('principal_balance');
        $principal_due = $this->input->post('principal_due');
        $penalty_due = $this->input->post('penalty_due');
        $accountHolderKey = $this->input->post('accountHolderKey');
        $productTypeKey = $this->input->post('productTypeKey');
        $fees_due = $this->input->post('fees_due');
        $payment_status = $this->input->post('payment_status');
        $transaction_date = $this->input->post('transaction_date');
        $transction_channel = $this->input->post('transaction_channel');
        $transaction_method = $this->input->post('transaction_method');

        $total_due = $principal_due + $interest_overdue + $fees_due + $penalty_due;
        $outstanding_balance = ($interest_overdue + $penalty_due + $interest_accrued + $fees_due);
        // $outstanding_balance = ($interest_overdue    ) + ($penalty_due) + ($fees_due) + ($principal_due);

        // collect all MBL loans 
        $mbls = [
            "8a9f86476efc4c9c016efc4c9c990001",
            "8a9f86476efc4c9c016efc5aea5f0169",
            "8a9f8708727950e1017279c41d4d0254",
            "8a9f8617713edafa01713f3375220411",
            "8a9f87007033e9ef0170343b577f0270",
            "8a9f8617713edafa01713f05d8d40153",
            "8a9f86626eeef668016eef4ad14d0511",
            "8a9f8708727950e1017279bba8540096",
            "8a9f86e071f58e890171f5ba08f50203",
            "8a9f86e071f58e890171f5b091b900ad",
            "8a9f86626eeef668016eef4a9a6e03d5",
            "8a9f86626eeef668016eef4ad14d0511"
        ];

        $weekly_mbls = [
            "8a9f86476efc4c9c016efc4c9c990001",
            "8a9f86476efc4c9c016efc5aea5f0169",
            "8a9f8708727950e1017279c41d4d0254",
            "8a9f8617713edafa01713f3375220411",
            "8a9f87007033e9ef0170343b577f0270",
            "8a9f8617713edafa01713f05d8d40153"
        ];
        $monthly_mbls = [
            "8a9f86626eeef668016eef4ad14d0511",
            "8a9f8708727950e1017279bba8540096",
            "8a9f86e071f58e890171f5ba08f50203",
            "8a9f86e071f58e890171f5b091b900ad",
            "8a9f86626eeef668016eef4a9a6e03d5",
            "8a9f86626eeef668016eef4ad14d0511"
        ];

        $repayments = $this->get_all_future_repayments($loan_id);
        
        if (empty($repayments)) {
            $first_fees_due = 0;
        } else {
            $get_first_repayment = $repayments[0];
            $first_fees_due = $get_first_repayment->feesDue;
        }

        if($outstanding_balance <= $liquidation_amount) {
            $reduced_principal = $liquidation_amount - $total_due; // remaining bulk amount after entering late repayment
            $principal_amount_to_deduct = $reduced_principal - ($interest_accrued + $first_fees_due); // Remaining principal after entering late repayments
            // $principal_amount_to_deduct = $principal_due - $principal_after_late;
            $new_principal_bal = $principal_balance - ($principal_due + $principal_amount_to_deduct);
            // $new_principal_bal =  abs($reduced_principal - $principal_due);
            $new_schedule = [];
            $rate = $interest_rate;  $new_principal_bal; $fv = 0; $type = 0; $fee_rate = (0.00 / 100);
            // $rate = 8.14; $new_tenure = 11; $new_principal_bal = 250000.00; $fv = 0; $type = 0; $fee_rate = (0.00 / 100);
            $tenor = $tenor > 0 ? $tenor : 1;
            // create a new repayment schedule
            if(in_array($productTypeKey, $mbls)) {
                if(in_array($productTypeKey, $weekly_mbls)) {
                    $schedule = $this->calculateSchedule(($rate/5), $tenor, $new_principal_bal, $fee_rate);
                } else {
                    $schedule = $this->calculateSchedule($rate, $tenor, $new_principal_bal, $fee_rate);
                }
            } else {
                $schedule = $this->calculateSchedule($rate, $tenor, $new_principal_bal, $fee_rate);
            }
            // $this->Base_model->dd($schedule);
            $schedule_id = uniqid('sch');

            $total_penalty_due = 0;
            $total_fees_due = 0;
            $interest = 0;

            $total_interest_due = 0;
            $repayments_interest_due = $this->get_all_future_repayments($loan_id);
            foreach($repayments_interest_due as $repayment) {
                $total_fees_due += ($repayment->feesDue - $repayment->feesPaid);
                $total_penalty_due += ($repayment->penaltyDue - $repayment->penaltyPaid);
                $total_interest_due += ($repayment->interestDue - $repayment->interestPaid);
            }

            $new_fees_due = ($total_fees_due - $fees_due) / $tenor;
            $new_penalty_due = ($total_penalty_due - $penalty_due) / $tenor;

            $late_repayment_interest = 0;
            $late_repayments_only = $this->get_all_late_repayments($loan_id);

            if(!empty($late_repayments_only)) {
                foreach($late_repayments_only as $repayment) {
                    $late_repayment_interest += $repayment->interestDue - $repayment->interestPaid;
                }
            }
            
            // $restructured_fees_due = $fees_due - 

            $interest = $interest / $tenor;
            foreach($repayments as $index => $repayment) {
                if($index <= $tenor) {
                    if($index == 0) {
                        $new_schedule[] = [
                            "schedule_id" => $schedule_id,
                            "encodedKey" => $repayment->encodedKey,
                            "interestDue" => $interest_accrued,
                            "principalDue" => 0,
                            "dueDate" => $repayment->dueDate,
                            "penaltyDue" => 0,
                            "feesDue" => $repayment->feesDue,
                            "parentAccountKey" => $repayment->parentAccountKey
                        ];
                        continue;
                    }
                    $new_schedule[] = [
                        "schedule_id" => $schedule_id,
                        "encodedKey" => $repayment->encodedKey,
                        "interestDue" => $schedule[$index-1]['interest'],
                        "principalDue" => $schedule[$index-1]['principal'],
                        "dueDate" => $repayment->dueDate,
                        "penaltyDue" => 0,
                        "feesDue" => $max_tenor > $tenor ? $new_fees_due : $repayment->feesDue,
                        "parentAccountKey" => $repayment->parentAccountKey
                    ];

                } else {
                    $new_schedule[] = [
                        "schedule_id" => $schedule_id,
                        "encodedKey" => $repayment->encodedKey,
                        "interestDue" => 0,
                        "principalDue" => 0,
                        "dueDate" => $repayment->dueDate,
                        "penaltyDue" => 0,
                        "feesDue" => 0,
                        "parentAccountKey" => $repayment->parentAccountKey
                    ];
                }
                
            }
            
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
                "principalBalance" => $new_principal_bal,
                "reducedPrincipal" => $principal_amount_to_deduct,
                "date_generated" => date('Y-m-d H:i:s'),
                "outstandingBalance" => $total_due,
                "interestBalance" => $reduced_principal //new reduced principal
            ];

            if($this->Base_model->create('loan_schedule', $loan_schedule)) {

                if($this->Base_model->createBatch('repayment_schedule', $new_schedule)) {
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
                json_encode("Liquidation amount is lower than total due")
            );
       
    }

    public function schedule_review($schedule_id) {
        if($loan_schedule = $this->Base_model->find("loan_schedule", ['schedule_id' => $schedule_id])) {
            $repayment_schedule = $this->Base_model->selectRepayment($loan_schedule->schedule_id);
            $mambu_loan_schedule = json_decode($this->Base_model->get_repayments($loan_schedule->loan_id), TRUE);
            $mambu_repayment_due = 0;
            $new_repayment_due = 0;
            foreach($mambu_loan_schedule as $repayment) {
                if($repayment['state'] == 'PENDING') {
                    $mambu_repayment_due = $repayment['principalDue'] + $repayment['interestDue'] + $repayment['feesDue'] + $repayment['penaltyDue'];
                }
            }

            foreach($repayment_schedule as $repayment) {
                $new_repayment_due = $repayment['principalDue'] + $repayment['interestDue'] + $repayment['feesDue'] + $repayment['penaltyDue'];
            break;
            }
            $data = [
                "loan_schedule" => $loan_schedule,
                "repayment_schedule" => $repayment_schedule,
                "new_repayment_due" => $new_repayment_due,
                "mambu_repayment_due" => $mambu_repayment_due,
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
            
        $loan_schedule = $this->Base_model->find("loan_schedule", ['schedule_id' => $schedule_id]);

        $client_details = json_decode($response, TRUE);
        
        $personal_email = $client_details['client']['emailAddress'];
        $client_name = $client_details['client']['firstName'];
        // $personal_email = "jadebayo@renmoney.com";


        $valid_till = date("Y-m-d H:i:s", strtotime("+24 hours"));
        $link = base64_encode(json_encode(['schedule_id' => $schedule_id, "valid_till" => $valid_till, "loan_id" => $loan]));
        $link = base_url() . "client/loan_schedule/$link";

        $content = "";
        if($loan_schedule->paymentStatus == "paid") {
            $content = '

                <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
                <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                <!--[if !mso]><!-->
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <!--<![endif]-->
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="format-detection" content="telephone=no">
                <meta name="x-apple-disable-message-reformatting">
                <title></title>
                <style type="text/css">
                    #outlook a {
                    padding: 0;
                    }
                
                    .ReadMsgBody,
                    .ExternalClass {
                    width: 100%;
                    }
                
                    .ExternalClass,
                    .ExternalClass p,
                    .ExternalClass td,
                    .ExternalClass div,
                    .ExternalClass span,
                    .ExternalClass font {
                    line-height: 100%;
                    }
                
                    div[style*="margin: 14px 0"],
                    div[style*="margin: 16px 0"] {
                    margin: 0 !important;
                    }
                
                    table,
                    td {
                    mso-table-lspace: 0;
                    mso-table-rspace: 0;
                    }
                
                    table,
                    tr,
                    td {
                    border-collapse: collapse;
                    }
                
                    body,
                    td,
                    th,
                    p,
                    div,
                    li,
                    a,
                    span {
                    -webkit-text-size-adjust: 100%;
                    -ms-text-size-adjust: 100%;
                    mso-line-height-rule: exactly;
                    }
                
                    img {
                    border: 0;
                    outline: none;
                    line-height: 100%;
                    text-decoration: none;
                    -ms-interpolation-mode: bicubic;
                    }
                
                    a[x-apple-data-detectors] {
                    color: inherit !important;
                    text-decoration: none !important;
                    }
                
                    body {
                    margin: 0;
                    padding: 0;
                    width: 100% !important;
                    -webkit-font-smoothing: antialiased;
                    }
                
                    .pc-gmail-fix {
                    display: none;
                    display: none !important;
                    }
                
                    @media screen and (min-width: 621px) {
                    .pc-email-container {
                        width: 620px !important;
                    }
                    }
                </style>
                <style type="text/css">
                    @media screen and (max-width:620px) {
                    .pc-sm-p-30 {
                        padding: 30px !important
                    }
                    .pc-sm-p-18-30 {
                        padding: 18px 30px !important
                    }
                    .pc-sm-p-20 {
                        padding: 20px !important
                    }
                    .pc-sm-p-35-10-30 {
                        padding: 35px 10px 30px !important
                    }
                    .pc-sm-mw-50pc {
                        max-width: 50% !important
                    }
                    .pc-sm-p-15-10 {
                        padding: 15px 10px !important
                    }
                    .pc-sm-ta-center {
                        text-align: center !important
                    }
                    .pc-sm-mw-100pc {
                        max-width: 100% !important
                    }
                    .pc-sm-p-20-20-0 {
                        padding: 20px 20px 0 !important
                    }
                    .pc-sm-p-16-20-20 {
                        padding: 16px 20px 20px !important
                    }
                    .pc-sm-m-0-auto {
                        margin: 0 auto !important
                    }
                    .pc-post-s2.pc-m-invert {
                        direction: ltr !important
                    }
                    .pc-sm-p-25-30-35 {
                        padding: 25px 30px 35px !important
                    }
                    .pc-sm-p-35-30 {
                        padding: 35px 30px !important
                    }
                    .pc-sm-p-31-20-39 {
                        padding: 31px 20px 39px !important
                    }
                    }
                </style>
                <style type="text/css">
                    @media screen and (max-width:525px) {
                    .pc-xs-p-0 {
                        padding: 0 !important
                    }
                    .pc-xs-p-25-20 {
                        padding: 25px 20px !important
                    }
                    .pc-xs-p-18-20 {
                        padding: 18px 20px !important
                    }
                    .pc-xs-p-10 {
                        padding: 10px !important
                    }
                    .pc-xs-p-25-0-20 {
                        padding: 25px 0 20px !important
                    }
                    .pc-xs-mw-100pc {
                        max-width: 100% !important
                    }
                    .pc-xs-br-disabled br {
                        display: none !important
                    }
                    .pc-xs-p-5-0 {
                        padding: 5px 0 !important
                    }
                    .pc-xs-w-100pc {
                        width: 100% !important
                    }
                    .pc-xs-p-10-0 {
                        padding: 10px 0 !important
                    }
                    .pc-xs-p-15-20-25 {
                        padding: 15px 20px 25px !important
                    }
                    .pc-xs-fs-30 {
                        font-size: 30px !important
                    }
                    .pc-xs-lh-42 {
                        line-height: 42px !important
                    }
                    .pc-xs-p-15-10-25 {
                        padding: 15px 10px 25px !important
                    }
                    }
                </style>
                <!--[if mso]>
                    <style type="text/css">
                        .pc-fb-font {
                            font-family: Helvetica, Arial, sans-serif !important;
                        }
                    </style>
                    <![endif]-->
                <!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
                </head>
                <body style="width: 100% !important; margin: 0; padding: 0; mso-line-height-rule: exactly; -webkit-font-smoothing: antialiased; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #e8e8e8" data-gr-c-s-loaded="true" data-new-gr-c-s-check-loaded="14.980.0" class="">
                <span style="color: transparent; display: none; height: 0; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all; visibility: hidden; width: 0;"></span>
                <div style="display: none !important; visibility: hidden; opacity: 0; overflow: hidden; mso-hide: all; height: 0; width: 0; max-height: 0; max-width: 0;">‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;</div>
                <table class="pc-email-body" width="100%" bgcolor="#e8e8e8" border="0" cellpadding="0" cellspacing="0" role="presentation" style="table-layout: fixed;">
                    <tbody>
                    <tr>
                        <td class="pc-email-body-inner" align="center" valign="top">
                        <!--[if gte mso 9]>
                            <v:background xmlns:v="urn:schemas-microsoft-com:vml" fill="t">
                                <v:fill type="tile" src="" color="#e8e8e8"></v:fill>
                            </v:background>
                            <![endif]-->
                        <!--[if (gte mso 9)|(IE)]><table width="620" align="center" border="0" cellspacing="0" cellpadding="0" role="presentation"><tr><td width="620" align="center" valign="top"><![endif]-->
                        <table class="pc-email-container" width="100%" align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto; max-width: 620px;">
                            <tbody>
                            <tr>
                                <td align="left" valign="top" style="padding: 0 10px;">
                                <table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation">
                                    <tbody>
                                    <tr>
                                        <td valign="top">
                                        <!-- BEGIN MODULE: Menu 9 -->
                                        <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td valign="top" bgcolor="#ffffff" style="background-color: #ffffff">
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                    <tbody>
                                                    <tr>
                                                        <td class="pc-sm-p-30 pc-xs-p-25-20" align="center" valign="top" style="padding: 30px 40px;">
                                                        <a href="https://renmoney.com" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/Logo_Color-EXY.png" width="130" height="" alt="" style="max-width: 100%; height: auto; border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; color: #1B1B1B;"></a>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td height="1" valign="top" bgcolor="#dedede" style="background-color: rgba(0, 0, 0, 0.1); font-size: 1px; line-height: 1px;">&nbsp;</td>
                                                    </tr>
                                                    </tbody>
                                                    <tbody>
                                                    <tr>
                                                        <td class="pc-fb-font" valign="top" style="padding: 0px 40px; text-align: center; line-height: 20px; font-family: Helvetica, sans-serif; font-size: 14px" pc-default-class="pc-sm-p-18-30 pc-xs-p-18-20 pc-fb-font" pc-default-padding="18px 40px">
                                                        <a href="https://renmoney.com/loans" style="text-decoration: none; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 500; color: #005d82">Loans</a>
                                                        <span class="pc-xs-p-0" style="padding: 0 23px;">&nbsp;&nbsp;</span>
                                                        <a href="https://renmoney.com/savings" style="text-decoration: none; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 500; color: #005d82">Savings</a>
                                                        <span class="pc-xs-p-0" style="padding: 0 23px;">&nbsp;&nbsp;</span>
                                                        <a href="https://renmoney.com/deposits" style="text-decoration: none; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 500; color: #005d82">Fixed Deposits</a>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Menu 9 -->
                                        <!-- BEGIN MODULE: Menu 6 -->
                                        <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td class="" bgcolor="#ffffff" valign="top" style="padding: 0px 30px; background-color: #ffffff" pc-default-class="pc-sm-p-20 pc-xs-p-10" pc-default-padding="25px 30px">
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                    <tbody>
                                                    <tr>
                                                        <td align="center" valign="top" style="padding: 10px;">
                                                        <a href="https://covid19.ncdc.gov.ng/" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/Test-4YC.png" width="300" height="" alt="" style="height: auto; max-width: 100%; border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; color: #1B1B1B; font-size: 14px;"></a>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Menu 6 -->
                                        <!-- BEGIN MODULE: undefined -->
                                        <!-- END MODULE: undefined -->
                                        <!-- BEGIN MODULE: Content 12 -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td class="" style="padding: 0px 20px; background-color: #035e82" valign="top" bgcolor="#035e82" pc-default-class="pc-sm-p-15-10 pc-xs-p-5-0" pc-default-padding="20px">
                                                <table class="pc-sm-ta-center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                                    <tbody>
                                                    <tr>
                                                        <td valign="top" style="padding: 20px;">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                                            <tbody>
                                                            <tr>
                                                                <td class="pc-fb-font" valign="top">
                                                                <span style="line-height: 34px; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 24px; font-weight: 700; letter-spacing: -0.4px; color: #ffffff">Part-liquidation of your Loan Account </span>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td height="0" style="font-size: 1px; line-height: 1px">&nbsp;</td>
                                                            </tr>
                                                            </tbody>
                                                            <tbody>
                                                            </tbody>
                                                        </table>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Content 12 -->
                                        <!-- BEGIN MODULE: Content 9 -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td class="" width="100%" valign="top" bgcolor="#ffffff" style="padding: 0px 40px 17px; background-color: #ffffff" pc-default-class="pc-sm-p-25-30-35 pc-xs-p-15-20-25" pc-default-padding="30px 40px 40px">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                                    <tbody>
                                                    </tbody>
                                                    <tbody>
                                                    <tr>
                                                        <td height="20" style="font-size: 1px; line-height: 1px;">&nbsp;</td>
                                                    </tr>
                                                    </tbody>
                                                    <tbody>
                                                    <tr>
                                                        <td class="pc-fb-font" style="line-height: 28px; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 300; letter-spacing: -0.2px; color: #9B9B9B" valign="top"><span style="color: #555555;">Hello '.$client_name.',</span><br><span style="color: #555555;">&nbsp;</span><br><span style="color: #555555;">Please see below the new repayment schedule based on your Part Liquidation Request:</span><br><span style="color: #555555;">Liquidation Amount: <span style="color: #686868;">₦</span>'.$liquidationAmount.'&nbsp;</span><br><span style="color: #555555;">Loan ID:'.$loan.'&nbsp;</span><br>
                                                        <a style="color: #555555;" href="'.$link.'"><span style="color: #015f82;">View</span> <span style="color: #015f82;">Schedule</span></a><span style="color: #555555;">&nbsp;</span><br><span style="color: #555555;">Click on the link above to Accept or Reject.&nbsp;</span><br><span style="color: #555555;">Please note that this link will expire after 24 hours.<br>&nbsp;</span><br>
                                                        <span style="color: #555555;">For more inquiries, contact <span style="color: #015f82;">hello@renmoney.com</span><br><br><strong><span style="color: #015f82;">The Renmoney Team&nbsp;</span></strong> </span>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Content 9 -->
                                        <!-- BEGIN MODULE: Footer 4 -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td class="" style="padding: 0px 30px; background-color: #005d82" valign="top" bgcolor="#005d82" pc-default-class="pc-sm-p-31-20-39 pc-xs-p-15-10-25" pc-default-padding="31px 30px 39px">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                                    <tbody>
                                                    <tr>
                                                        <td valign="top" style="font-size: 0;">
                                                        <!--[if (gte mso 9)|(IE)]><table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation"><tr><td width="433" valign="top"><![endif]-->
                                                        <div class="pc-sm-mw-100pc" style="display: inline-block; width: 100%; max-width: 433px; vertical-align: top;">
                                                            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                            <tbody>
                                                                <tr>
                                                                <td class="pc-fb-font" style="line-height: 20px; letter-spacing: -0.2px; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 14px; padding: 10px; color: #ffffff" valign="top">23, Awolowo Road, Ikoyi, Lagos.<br>hello@renmoney.com<br>www.renmoney.com<br>0700 5000 500</td>
                                                                </tr>
                                                            </tbody>
                                                            </table>
                                                        </div>
                                                        <!--[if (gte mso 9)|(IE)]></td><td width="107" valign="top"><![endif]-->
                                                        <div class="pc-sm-mw-100pc" style="display: inline-block; width: 100%; max-width: 107px; vertical-align: top;">
                                                            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                            <tbody>
                                                                <tr>
                                                                <td valign="top" style="padding: 9px 0 10px;">
                                                                    <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                                    <tbody>
                                                                        <tr>
                                                                        <td valign="middle" style="padding: 0 10px;">
                                                                            <a href="https://www.facebook.com/renmoney/" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/facebook-white.png" width="15" height="15" alt="" style="border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; font-size: 14px; color: #ffffff;"></a>
                                                                        </td>
                                                                        <td valign="middle" style="padding: 0 10px;">
                                                                            <a href="https://twitter.com/Renmoney" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/twitter-white.png" width="16" height="14" alt="" style="border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; font-size: 14px; color: #ffffff;"></a>
                                                                        </td>
                                                                        <td valign="middle" style="padding: 0 10px;">
                                                                            <a href="https://www.instagram.com/renmoneyng/" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/instagram-white.png" width="16" height="15" alt="" style="border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; font-size: 14px; color: #ffffff;"></a>
                                                                        </td>
                                                                        </tr>
                                                                    </tbody>
                                                                    </table>
                                                                </td>
                                                                </tr>
                                                            </tbody>
                                                            </table>
                                                        </div>
                                                        <!--[if (gte mso 9)|(IE)]></td></tr></table><![endif]-->
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                    <tbody>
                                                    <tr>
                                                        <td height="0" style="font-size: 1px; line-height: 1px">&nbsp;</td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Footer 4 -->
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <!--[if (gte mso 9)|(IE)]></td></tr></table><![endif]-->
                        </td>
                    </tr>
                    </tbody>
                </table>
                <!-- Fix for Gmail on iOS -->
                <div class="pc-gmail-fix" style="white-space: nowrap; font: 15px courier; line-height: 0;">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; </div>
                </body>
                </html>
            ';
        } else {
            $content = '

                <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
                <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
                <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                <!--[if !mso]><!-->
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <!--<![endif]-->
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="format-detection" content="telephone=no">
                <meta name="x-apple-disable-message-reformatting">
                <title></title>
                <style type="text/css">
                    #outlook a {
                    padding: 0;
                    }
                
                    .ReadMsgBody,
                    .ExternalClass {
                    width: 100%;
                    }
                
                    .ExternalClass,
                    .ExternalClass p,
                    .ExternalClass td,
                    .ExternalClass div,
                    .ExternalClass span,
                    .ExternalClass font {
                    line-height: 100%;
                    }
                
                    div[style*="margin: 14px 0"],
                    div[style*="margin: 16px 0"] {
                    margin: 0 !important;
                    }
                
                    table,
                    td {
                    mso-table-lspace: 0;
                    mso-table-rspace: 0;
                    }
                
                    table,
                    tr,
                    td {
                    border-collapse: collapse;
                    }
                
                    body,
                    td,
                    th,
                    p,
                    div,
                    li,
                    a,
                    span {
                    -webkit-text-size-adjust: 100%;
                    -ms-text-size-adjust: 100%;
                    mso-line-height-rule: exactly;
                    }
                
                    img {
                    border: 0;
                    outline: none;
                    line-height: 100%;
                    text-decoration: none;
                    -ms-interpolation-mode: bicubic;
                    }
                
                    a[x-apple-data-detectors] {
                    color: inherit !important;
                    text-decoration: none !important;
                    }
                
                    body {
                    margin: 0;
                    padding: 0;
                    width: 100% !important;
                    -webkit-font-smoothing: antialiased;
                    }
                
                    .pc-gmail-fix {
                    display: none;
                    display: none !important;
                    }
                
                    @media screen and (min-width: 621px) {
                    .pc-email-container {
                        width: 620px !important;
                    }
                    }
                </style>
                <style type="text/css">
                    @media screen and (max-width:620px) {
                    .pc-sm-p-30 {
                        padding: 30px !important
                    }
                    .pc-sm-p-18-30 {
                        padding: 18px 30px !important
                    }
                    .pc-sm-p-20 {
                        padding: 20px !important
                    }
                    .pc-sm-p-35-10-30 {
                        padding: 35px 10px 30px !important
                    }
                    .pc-sm-mw-50pc {
                        max-width: 50% !important
                    }
                    .pc-sm-p-15-10 {
                        padding: 15px 10px !important
                    }
                    .pc-sm-ta-center {
                        text-align: center !important
                    }
                    .pc-sm-mw-100pc {
                        max-width: 100% !important
                    }
                    .pc-sm-p-20-20-0 {
                        padding: 20px 20px 0 !important
                    }
                    .pc-sm-p-16-20-20 {
                        padding: 16px 20px 20px !important
                    }
                    .pc-sm-m-0-auto {
                        margin: 0 auto !important
                    }
                    .pc-post-s2.pc-m-invert {
                        direction: ltr !important
                    }
                    .pc-sm-p-25-30-35 {
                        padding: 25px 30px 35px !important
                    }
                    .pc-sm-p-35-30 {
                        padding: 35px 30px !important
                    }
                    .pc-sm-p-31-20-39 {
                        padding: 31px 20px 39px !important
                    }
                    }
                </style>
                <style type="text/css">
                    @media screen and (max-width:525px) {
                    .pc-xs-p-0 {
                        padding: 0 !important
                    }
                    .pc-xs-p-25-20 {
                        padding: 25px 20px !important
                    }
                    .pc-xs-p-18-20 {
                        padding: 18px 20px !important
                    }
                    .pc-xs-p-10 {
                        padding: 10px !important
                    }
                    .pc-xs-p-25-0-20 {
                        padding: 25px 0 20px !important
                    }
                    .pc-xs-mw-100pc {
                        max-width: 100% !important
                    }
                    .pc-xs-br-disabled br {
                        display: none !important
                    }
                    .pc-xs-p-5-0 {
                        padding: 5px 0 !important
                    }
                    .pc-xs-w-100pc {
                        width: 100% !important
                    }
                    .pc-xs-p-10-0 {
                        padding: 10px 0 !important
                    }
                    .pc-xs-p-15-20-25 {
                        padding: 15px 20px 25px !important
                    }
                    .pc-xs-fs-30 {
                        font-size: 30px !important
                    }
                    .pc-xs-lh-42 {
                        line-height: 42px !important
                    }
                    .pc-xs-p-15-10-25 {
                        padding: 15px 10px 25px !important
                    }
                    }
                </style>
                <!--[if mso]>
                    <style type="text/css">
                        .pc-fb-font {
                            font-family: Helvetica, Arial, sans-serif !important;
                        }
                    </style>
                    <![endif]-->
                <!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
                </head>
                <body style="width: 100% !important; margin: 0; padding: 0; mso-line-height-rule: exactly; -webkit-font-smoothing: antialiased; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #e8e8e8" data-gr-c-s-loaded="true" data-new-gr-c-s-check-loaded="14.980.0" class="">
                <span style="color: transparent; display: none; height: 0; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; mso-hide: all; visibility: hidden; width: 0;"></span>
                <div style="display: none !important; visibility: hidden; opacity: 0; overflow: hidden; mso-hide: all; height: 0; width: 0; max-height: 0; max-width: 0;">‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;‌&nbsp;</div>
                <table class="pc-email-body" width="100%" bgcolor="#e8e8e8" border="0" cellpadding="0" cellspacing="0" role="presentation" style="table-layout: fixed;">
                    <tbody>
                    <tr>
                        <td class="pc-email-body-inner" align="center" valign="top">
                        <!--[if gte mso 9]>
                            <v:background xmlns:v="urn:schemas-microsoft-com:vml" fill="t">
                                <v:fill type="tile" src="" color="#e8e8e8"></v:fill>
                            </v:background>
                            <![endif]-->
                        <!--[if (gte mso 9)|(IE)]><table width="620" align="center" border="0" cellspacing="0" cellpadding="0" role="presentation"><tr><td width="620" align="center" valign="top"><![endif]-->
                        <table class="pc-email-container" width="100%" align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto; max-width: 620px;">
                            <tbody>
                            <tr>
                                <td align="left" valign="top" style="padding: 0 10px;">
                                <table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation">
                                    <tbody>
                                    <tr>
                                        <td valign="top">
                                        <!-- BEGIN MODULE: Menu 9 -->
                                        <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td valign="top" bgcolor="#ffffff" style="background-color: #ffffff">
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                    <tbody>
                                                    <tr>
                                                        <td class="pc-sm-p-30 pc-xs-p-25-20" align="center" valign="top" style="padding: 30px 40px;">
                                                        <a href="https://renmoney.com" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/Logo_Color-EXY.png" width="130" height="" alt="" style="max-width: 100%; height: auto; border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; color: #1B1B1B;"></a>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td height="1" valign="top" bgcolor="#dedede" style="background-color: rgba(0, 0, 0, 0.1); font-size: 1px; line-height: 1px;">&nbsp;</td>
                                                    </tr>
                                                    </tbody>
                                                    <tbody>
                                                    <tr>
                                                        <td class="pc-fb-font" valign="top" style="padding: 0px 40px; text-align: center; line-height: 20px; font-family: Helvetica, sans-serif; font-size: 14px" pc-default-class="pc-sm-p-18-30 pc-xs-p-18-20 pc-fb-font" pc-default-padding="18px 40px">
                                                        <a href="https://renmoney.com/loans" style="text-decoration: none; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 500; color: #005d82">Loans</a>
                                                        <span class="pc-xs-p-0" style="padding: 0 23px;">&nbsp;&nbsp;</span>
                                                        <a href="https://renmoney.com/savings" style="text-decoration: none; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 500; color: #005d82">Savings</a>
                                                        <span class="pc-xs-p-0" style="padding: 0 23px;">&nbsp;&nbsp;</span>
                                                        <a href="https://renmoney.com/deposits" style="text-decoration: none; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 500; color: #005d82">Fixed Deposits</a>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Menu 9 -->
                                        <!-- BEGIN MODULE: Menu 6 -->
                                        <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td class="" bgcolor="#ffffff" valign="top" style="padding: 0px 30px; background-color: #ffffff" pc-default-class="pc-sm-p-20 pc-xs-p-10" pc-default-padding="25px 30px">
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                    <tbody>
                                                    <tr>
                                                        <td align="center" valign="top" style="padding: 10px;">
                                                        <a href="https://covid19.ncdc.gov.ng/" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/Test-4YC.png" width="300" height="" alt="" style="height: auto; max-width: 100%; border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; color: #1B1B1B; font-size: 14px;"></a>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Menu 6 -->
                                        <!-- BEGIN MODULE: undefined -->
                                        <!-- END MODULE: undefined -->
                                        <!-- BEGIN MODULE: Content 12 -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td class="" style="padding: 0px 20px; background-color: #035e82" valign="top" bgcolor="#035e82" pc-default-class="pc-sm-p-15-10 pc-xs-p-5-0" pc-default-padding="20px">
                                                <table class="pc-sm-ta-center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                                    <tbody>
                                                    <tr>
                                                        <td valign="top" style="padding: 20px;">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                                            <tbody>
                                                            <tr>
                                                                <td class="pc-fb-font" valign="top">
                                                                <span style="line-height: 34px; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 24px; font-weight: 700; letter-spacing: -0.4px; color: #ffffff">Part-liquidation of your Loan Account </span>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td height="0" style="font-size: 1px; line-height: 1px">&nbsp;</td>
                                                            </tr>
                                                            </tbody>
                                                            <tbody>
                                                            </tbody>
                                                        </table>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Content 12 -->
                                        <!-- BEGIN MODULE: Content 9 -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td class="" width="100%" valign="top" bgcolor="#ffffff" style="padding: 0px 40px 17px; background-color: #ffffff" pc-default-class="pc-sm-p-25-30-35 pc-xs-p-15-20-25" pc-default-padding="30px 40px 40px">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                                    <tbody>
                                                    </tbody>
                                                    <tbody>
                                                    <tr>
                                                        <td height="20" style="font-size: 1px; line-height: 1px;">&nbsp;</td>
                                                    </tr>
                                                    </tbody>
                                                    <tbody>
                                                    <tr>
                                                        <td class="pc-fb-font" style="line-height: 28px; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 300; letter-spacing: -0.2px; color: #9B9B9B" valign="top"><span style="color: #555555;">Hello '.$client_name.',</span><br><span style="color: #555555;">&nbsp;</span><br><span style="color: #555555;">Please see below the new repayment schedule based on your Part Liquidation Request:</span><br><span style="color: #555555;">Liquidation Amount: <span style="color: #686868;">₦</span>'.$liquidationAmount.'&nbsp;</span><br><span style="color: #555555;">Loan ID:'.$loan.'&nbsp;</span><br>
                                                        <a style="color: #555555;" href="'.$link.'"><span style="color: #015f82;">View</span> <span style="color: #015f82;">Schedule</span></a><span style="color: #555555;">&nbsp;</span><br><span style="color: #555555;">Click on the link above to Accept or Reject.&nbsp;</span><br><span style="color: #555555;">Please note that this link will expire after 24 hours.<br>&nbsp;</span><br>
                                                        <span style="color: #555555;">For more inquiries, contact <span style="color: #015f82;">hello@renmoney.com</span><br><br><strong><span style="color: #015f82;">The Renmoney Team&nbsp;</span></strong> </span>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Content 9 -->
                                        <!-- BEGIN MODULE: Footer 4 -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                            <tbody>
                                            <tr>
                                                <td class="" style="padding: 0px 30px; background-color: #005d82" valign="top" bgcolor="#005d82" pc-default-class="pc-sm-p-31-20-39 pc-xs-p-15-10-25" pc-default-padding="31px 30px 39px">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                                    <tbody>
                                                    <tr>
                                                        <td valign="top" style="font-size: 0;">
                                                        <!--[if (gte mso 9)|(IE)]><table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation"><tr><td width="433" valign="top"><![endif]-->
                                                        <div class="pc-sm-mw-100pc" style="display: inline-block; width: 100%; max-width: 433px; vertical-align: top;">
                                                            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                            <tbody>
                                                                <tr>
                                                                <td class="pc-fb-font" style="line-height: 20px; letter-spacing: -0.2px; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 14px; padding: 10px; color: #ffffff" valign="top">23, Awolowo Road, Ikoyi, Lagos.<br>hello@renmoney.com<br>www.renmoney.com<br>0700 5000 500</td>
                                                                </tr>
                                                            </tbody>
                                                            </table>
                                                        </div>
                                                        <!--[if (gte mso 9)|(IE)]></td><td width="107" valign="top"><![endif]-->
                                                        <div class="pc-sm-mw-100pc" style="display: inline-block; width: 100%; max-width: 107px; vertical-align: top;">
                                                            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                            <tbody>
                                                                <tr>
                                                                <td valign="top" style="padding: 9px 0 10px;">
                                                                    <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                                    <tbody>
                                                                        <tr>
                                                                        <td valign="middle" style="padding: 0 10px;">
                                                                            <a href="https://www.facebook.com/renmoney/" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/facebook-white.png" width="15" height="15" alt="" style="border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; font-size: 14px; color: #ffffff;"></a>
                                                                        </td>
                                                                        <td valign="middle" style="padding: 0 10px;">
                                                                            <a href="https://twitter.com/Renmoney" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/twitter-white.png" width="16" height="14" alt="" style="border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; font-size: 14px; color: #ffffff;"></a>
                                                                        </td>
                                                                        <td valign="middle" style="padding: 0 10px;">
                                                                            <a href="https://www.instagram.com/renmoneyng/" style="text-decoration: none;"><img src="https://designmodo-postcards-prod.s3.amazonaws.com/instagram-white.png" width="16" height="15" alt="" style="border: 0; line-height: 100%; outline: 0; -ms-interpolation-mode: bicubic; font-size: 14px; color: #ffffff;"></a>
                                                                        </td>
                                                                        </tr>
                                                                    </tbody>
                                                                    </table>
                                                                </td>
                                                                </tr>
                                                            </tbody>
                                                            </table>
                                                        </div>
                                                        <!--[if (gte mso 9)|(IE)]></td></tr></table><![endif]-->
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                    <tbody>
                                                    <tr>
                                                        <td height="0" style="font-size: 1px; line-height: 1px">&nbsp;</td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!-- END MODULE: Footer 4 -->
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <!--[if (gte mso 9)|(IE)]></td></tr></table><![endif]-->
                        </td>
                    </tr>
                    </tbody>
                </table>
                <!-- Fix for Gmail on iOS -->
                <div class="pc-gmail-fix" style="white-space: nowrap; font: 15px courier; line-height: 0;">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; </div>
                </body>
                </html>
            ';
        }
        
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


    function calculateSchedule($rate, $nper, $pv, $fee_rate) {
        $opening_bal = $this->openingBal($pv, $fee_rate);
        $monthly_payment = $this->getMonthlyPayment($rate, $nper, $opening_bal);
        $schedule = [];

        $initial_bal = $opening_bal; 
    

        for($i = 1; $i <= $nper; $i++) {
            

            $interest = round(($initial_bal * ($rate / 100)), 2);
            if($i == $nper) {
                $principal = $initial_bal;
            } else {
                $principal = round(($monthly_payment - $interest), 2);
            }
            $new_balance = round(($initial_bal - $principal), 2);

            $schedule[] = [
                'opening_balance' => $initial_bal,
                'monthly_payment' => $monthly_payment,
                'principal' => $principal,
                'interest' => $interest,
                'balance' => $new_balance,
            ];

            $initial_bal = $new_balance > 0 ? $new_balance : 0;            
        }

        return $schedule;
    }


    function getMonthlyPayment($interest, $tenure, $PV, $FV = 0.00, $type = 0){
        $interest = ($interest / 100);
        $xp = pow((1 + $interest), $tenure);
        return (
            $PV * $interest * $xp / ($xp - 1) + $interest / ($xp - 1) * $FV) *
            ($type == 0 ? 1 : 1 / ($interest + 1)
        );
    }

    function openingBal($amount, $feeRate) {
        return $amount * (1 + $feeRate);
    }


}