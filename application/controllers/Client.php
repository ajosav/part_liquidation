<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Client extends CI_Controller {

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

    public function loan_schedule($url = "") {
        if(base64_encode(base64_decode($url)) != $url) {
            $this->load->view('part_liquidation/client/client_link_error'); 
            return false;
        }
        
        $link = json_decode(base64_decode($url));

        
        // $this->Base_model->dd($back_date);
        
        $schedule_id = $link->schedule_id;
        $valid_till = $link->valid_till;
        
        if(!$loan_schedule = $this->Base_model->find("loan_schedule", ['schedule_id' => $schedule_id])) {
            $this->load->view('part_liquidation/client/client_link_error');
            return false;
        }
        // $datetime = date('Y-m-d H:i:s',strtotime('1 hour', strtotime($today)));
        $back_date = date('Y-m-d H:i:s', strtotime("-24 hours", strtotime($this->today)));
        $validity = date('Y-m-d H:i:s', strtotime("+24 hours", strtotime($loan_schedule->date_generated)));
        if($back_date > $valid_till || $validity > $valid_till ) {
            $this->load->view('part_liquidation/client/client_link_error');
            return false;
        }
        
        $repayment_schedule = $this->Base_model->selectRepayment($loan_schedule->schedule_id);
        $client_encoded_key = $loan_schedule->accountHolderKey;

        $endpointURL = $this->mambu_base_url . "api/clients/" . $client_encoded_key ."?fullDetails=true";
        $response = $this->Base_model->call_mambu_api_get($endpointURL);
			
        $client_details = json_decode($response, TRUE);
        $data = [
            "loan_schedule" => $loan_schedule,
            "repayment_schedule" => $repayment_schedule,
            "client_details" => $client_details
        ];

        $this->load->view('part_liquidation/meta_link');
        $this->load->view('part_liquidation/client/preview_schedule', $data); 
        $this->load->view('part_liquidation/footer_link');
    }

   
   

    public function reject_schedule() {
        $reason = $this->input->post('rejection_reason');
        $schedule_id = $this->input->post('schedule_id');
        $rejection_state = $this->input->post('rejection_state');
        $client_fname = $this->input->post('client_fname');
        $client_lname = $this->input->post('client_lname');
        $client_email = $this->input->post('client_email');
        $client_phone = $this->input->post('client_phone');
        $loan_id = $this->input->post('loan_id');

        $email = ["EObukohwo@renmoney.com"];
        $cc = [];
        
        $status = 3;

        $data = [
            'status' => $status
        ];

        $team_email_body = [
            "recipient" => $email,
            "subject" => "Rejected Repayment Schedule",
            "content" => "
                <p> <img src ='https://www.renmoneyng.com/images/uploads/email-template-top.png' alt = '' /> </p>
                <p>Dear Team</p>
                <p>Please note that {$client_fname} {$client_lname} has rejected the New repayment schedule. </br>
                Reason: {$rejection_state}
                Details: {$reason} 
                <p>Best Regards, <br>
                The Renmoney Team </p>
                <p>  <img src ='https://www.renmoneyng.com/images/uploads/email-template-bottom.png' alt = '' /> </p>
            ",
            "cc" => $cc,
            "category" => ['Part-liquidation']
        ];

        $refund_email_body = [
            "recipient" => [$client_email],
            "subject" => "New Repayment Schedule Rejected",
            "content" => "
                <p> <img src ='https://www.renmoneyng.com/images/uploads/email-template-top.png' alt = '' /> </p>
                <p>Dear {$client_fname}</p>
                <p>You have declined the part-liquidation of your loan. <br>
                Your bulk payment will be refunded within 24 working hours. <br>
                For any enquiries, contact hello@renmoney.com</p>
                <p>Thank you for choosing RenMoney MFB LTD. </p>
                <p>  <img src ='https://www.renmoneyng.com/images/uploads/email-template-bottom.png' alt = '' /> </p>
            ",
            "cc" => $cc,
            "category" => ['Part-liquidation']
        ];

        $repayment_email_body = [
            "recipient" => [$client_email],
            "subject" => "New Repayment Schedule Rejected",
            "content" => "
                <p> <img src ='https://www.renmoneyng.com/images/uploads/email-template-top.png' alt = '' /> </p>
                <p>Dear {$client_fname}</p>
                <p>You have declined the part-liquidation of your loan. <br>
                Your bulk payment will be effected as repayments within 24 working hours. <br>
                For any enquiries, contact hello@renmoney.com</p>
                <p>Thank you for choosing RenMoney MFB LTD. </p>
                <p>  <img src ='https://www.renmoneyng.com/images/uploads/email-template-bottom.png' alt = '' /> </p>
            ",
            "cc" => $cc,
            "category" => ['Part-liquidation']
        ];

            if($this->Base_model->update_table('loan_schedule', ['schedule_id' => $schedule_id], $data)) {
                $this->Base_model->notifyMail($team_email_body);
                if($rejection_state == "refund") {
                    $this->Base_model->notifyMail($refund_email_body);
                    
                } else {
                    $this->Base_model->notifyMail($repayment_email_body);
                }
                
                return $this->output
                ->set_content_type('application/json')
                ->set_status_header(200)
                ->set_output(
                    json_encode('Liquidation Schedule Successfully declined')
                );
            }
            
        return $this->output
        ->set_content_type('application/json')
        ->set_status_header(400)
        ->set_output(
            json_encode('Back process could not be declined for some reason')
        );
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

    public function accept_schedule() {
        if($this->input->method() != "post") {
            redirect(base_url().'Client/loan_schedule');
        }
        $schedule_id = $this->input->post('schedule_id');
        $client_fname = $this->input->post('client_fname');
        $client_lname = $this->input->post('client_lname');
        $client_email = $this->input->post('client_email');
        $client_phone = $this->input->post('client_phone');
        $loan_id = $this->input->post('loan_id');

        // $client_phone = "2348137512747";
        // $client_email = "ajosavboy@gmail.com";

        $this->Base_model->update_table('loan_schedule', ['schedule_id' => $schedule_id], ['status' => 4]);

        $this->send_otp($client_phone, $client_email, $client_fname);
       
        $data = [
            "schedule_id" => $schedule_id,
            "client_fname" => $client_fname,
            "client_lname" => $client_lname,
            "client_email" => $client_email,
            "phone" => $this->maskPhoneNumber($client_phone),
            "loan_id" => $loan_id
        ];
        $this->load->view('part_liquidation/meta_link');
        $this->load->view('part_liquidation/client/otp_verification', $data); 
        $this->load->view('part_liquidation/footer_link');
      
    }

    public function resend_otp() {
        if($this->input->method() != "post") {
            redirect(base_url().'Client/loan_schedule');
        }

        $schedule_id = $this->input->post('schedule_id');
        $client_fname = $this->input->post('client_fname');
        $client_lname = $this->input->post('client_lname');
        $client_email = $this->input->post('client_email');
        $loan_id = $this->input->post('loan_id');
        $loan_schedule = $this->Base_model->find('loan_schedule', ['schedule_id' => $schedule_id]);
        $client_encoded_key = $loan_schedule->accountHolderKey;

        $endpointURL = $this->mambu_base_url . "api/clients/" . $client_encoded_key ."?fullDetails=true";
        $response = $this->Base_model->call_mambu_api_get($endpointURL);
        $response = json_decode($response, TRUE);
        if($response && !isset($response['return_code'])) {

            $client_phone = $response['client']['mobilePhone1'];
    
            $this->send_otp($client_phone, $client_email, $client_fname);

            return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(
                json_encode('OTP resent to your email and phone')
            );  
        }

        return $this->output
        ->set_content_type('application/json')
        ->set_status_header(400)
        ->set_output(
            json_encode('Connection Lost')
        );       
    }

    public function otp_validation() {
        set_time_limit(0);
        if($this->input->method() != "post") {
            redirect(base_url().'Client/loan_schedule');
        }

        $schedule_id = $this->input->post('schedule_id');
        $client_fname = $this->input->post('client_fname');
        $client_lname = $this->input->post('client_lname');
        $client_email = $this->input->post('client_email');
        $loan_id = $this->input->post('loan_id');
        $otp_code = $this->input->post('otp');

        if(!$otp_info = $this->Base_model->find("otp", ['otp' => $otp_code])) {
            return $this->output
            ->set_content_type('application/json')
            ->set_status_header(400)
            ->set_output(
                json_encode('Invalid OTP')
            );
        }
        $duration = (int) $otp_info->duration;

        $back_time = date('Y-m-d H:i:s', strtotime("-{$duration} mins"));

        if($back_time > $otp_info->date_created) {
            return $this->output
            ->set_content_type('application/json')
            ->set_status_header(400)
            ->set_output(
                json_encode('Invalid OTP')
            );
        }

        if(!$loan_schedule = $this->Base_model->find("loan_schedule", ['schedule_id' => $schedule_id])) {
            return $this->output
            ->set_content_type('application/json')
            ->set_status_header(400)
            ->set_output(
                json_encode('Error Completing your liquidation process')
            );
        }

        $transaction_url = $this->mambu_base_url."api/loans/{$loan_id}/transactions";
        
        if($loan_schedule->paymentStatus == "unpaid") {
            return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(
                json_encode('Your Part liquidation is queued, please proceed to payment')
            );
            
        }

        $repayment_data = [
            "type" => "REPAYMENT",
            "amount" => round($loan_schedule->outstandingBalance, 2),
            "date" => date('Y-m-d', strtotime($loan_schedule->transactionDate)),
            "method" => $loan_schedule->transactionChannel,
            "customInformation" => [
                [
                    "value" => $loan_schedule->transaction_method,
                    "customFieldID" => "Repayment_Method_Transactions"
                ]
            ]
        ];

        $this->Base_model->dd($repayment_data);

        $response = json_decode($this->Base_model->call_mambu_api($transaction_url, $repayment_data), TRUE);

        if(isset($response['returnCode'])) {
            $data = [
                "message" => "Failed to pay outstanding Balance",
                "details" => json_encode($response['returnStatus']),
                "schedule_id" => $schedule_id,
                "loan_id" => $loan_id,
                "date" => date('Y-m-d H:i:s')
            ];
            $this->Base_model->create('liquidation_log', $data);
            return $this->output
            ->set_content_type('application/json')
            ->set_status_header(400)
            ->set_output(
                json_encode('Your Part liquidation could not be completed')
            );
        }

        $data = [
            "message" => "Outstanding Repayments settled",
            "details" => $response,
            "schedule_id" => $schedule_id,
            "loan_id" => $loan_id,
            "date" => date('Y-m-d H:i:s')
        ];
        // $this->Base_model->create('liquidation_log', $data);

        $repayments = $this->Base_model->findWhere('repayment_schedule', ['schedule_id' => $schedule_id]);

        $collect_repayment = [];
        foreach($repayments as $repayment) {
            $collect_repayment['repayments'][] = [
                "encodedKey" => $repayment['encodedKey'],
                "principalDue" => round($repayment['principalDue'], 2),
                "penaltyDue" => round($repayment['principalDue'], 2),
                "interestDue" => round($repayment['principalDue'], 2),
                "feesDue" => round($repayment['principalDue'], 2),
                "parentAccountKey" => $repayment['parentAccountKey'],
            ];
        }

        $this->Base_model->dd($collect_repayment['repayments']);






        
        // $this->Base_model->

       
    }

    private function send_otp($client_phone, $client_email, $client_fname) {
        $otp = rand(100000, 999999);
        $this->Base_model->create(
            "otp",
            [
                "otp" => $otp,
                "receiver_no" => $client_phone,
                "duration" => 15,
                "date_created" => date('Y-m-d H:i:s'),
            ]
        );
        $otp_mail_body = [
            "recipient" => [$client_email],
            "subject" => "Renmoney MFB LTD. One Time Password",
            "content" => "
                <p> <img src ='https://www.renmoneyng.com/images/uploads/email-template-top.png' alt = '' /> </p>
                <p>Dear {$client_fname}</p>
                <p>This is your one time password {$otp} <br>
                    if you did not initiate this process, please contact hello@renmoney.com
                </p>
                <p>Thank you for choosing RenMoney MFB LTD. </p>
                <p>  <img src ='https://www.renmoneyng.com/images/uploads/email-template-bottom.png' alt = '' /> </p>
            ",
            "cc" => [],
            "category" => ['Part-liquidation']
        ];

        $otp_body = [
            "phone_number" => $client_phone,
            "message" => "Renmoney Loan Liquidation (OTP): {$otp}"
        ];

        $this->Base_model->sendSms($otp_body);
        $this->Base_model->notifyMail($otp_mail_body);
    }

    private function maskPhoneNumber($number){
        $length = strlen($number);
        $middle_string ="";
        if( $length < 3 ){

            return $length == 1 ? "*" : "*". substr($number,  - 1);

        }
        else{
            $part_size = floor( $length / 3 ) ; 
            $middle_part_size = $length - ( $part_size * 2 );
            for( $i=0; $i < $middle_part_size ; $i ++ ){
                $middle_string .= "*";
            }

            return  substr($number, 0, $part_size ) . $middle_string  . substr($number,  - $part_size );
        }
        // $mask_number =  str_repeat("*", strlen($number)-4) . substr($number, -4);
        
        // return $mask_number;
    }
	

}