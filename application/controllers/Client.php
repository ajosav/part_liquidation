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

        
        
        $schedule_id = $link->schedule_id;
        $valid_till = $link->valid_till;

        $valid_till = "2020-10-03";

        if(!$loan_schedule = $this->Base_model->find("loan_schedule", ['schedule_id' => $schedule_id])) {
            $this->load->view('part_liquidation/client/client_link_error');
            return false;
        }
        // $datetime = date('Y-m-d H:i:s',strtotime('1 hour', strtotime($today)));
        $back_date = date('Y-m-d H:i:s', strtotime("-24 hours", strtotime($this->today)));
        $link_date = date('Y-m-d H:i:s', strtotime("-24 hours", strtotime($valid_till)));
        $validity = date('Y-m-d H:i:s', strtotime($loan_schedule->date_generated));
        // if($back_date < $valid_till || $validity > $valid_till ) {
        if(($back_date > $link_date) || ($link_date < $validity)) {
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

        $reject_state_prefix = $rejection_state == "refund" ? '' : "Apply as";

        $team_mail_body = '
        <div style="font-family: verdana, Trebuchet ms, arial; line-height: 1.5em>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
                <p style="margin-top:0;margin-bottom:0;">Dear Team</p>
                <p style="margin-top:0;margin-bottom:0;">Please note that '.$client_fname. ' '. $client_lname .' has rejected the New repayment schedule. </br>
                Reason: '.$reject_state_prefix.' '.$rejection_state.'<br>
                Details: '.$reason.'

                <p style="margin-top:0;margin-bottom:0;">
                <b>Best Regards, <br>
                The Renmoney Team </b>
                </p>

            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
            <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
        </div>';

        $team_email_body = [
            "recipient" => $email,
            "subject" => "Rejected Repayment Schedule",
            "content" => $team_mail_body,
            "cc" => $cc,
            "category" => ['Part-liquidation']
        ];

        $refund_mail_content ='
        <div style="font-family: verdana, Trebuchet ms, arial; line-height: 1.5em>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
                <p style="margin-top:0;margin-bottom:0;">Dear '.$client_fname.'</p>
                <p style="margin-top:0;margin-bottom:0;">You have declined the part-liquidation of your loan. <br>
                Your bulk payment will be refunded within 24 working hours. <br>
                For any enquiries, contact hello@renmoney.com</p>

                <p style="margin-top:0;margin-bottom:0;"><b>Thank you for choosing RenMoney MFB LTD. </b></p>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
            <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
        </div>';
        $refund_email_body = [
            "recipient" => [$client_email],
            "subject" => "New Repayment Schedule Rejected",
            "content" => $refund_mail_content,
            "cc" => $cc,
            "category" => ['Part-liquidation']
        ];

        $repayment_mail_content = '
        <div style="font-family: verdana, Trebuchet ms, arial; line-height: 1.5em>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
            <p style="margin-top:0;margin-bottom:0;">Dear '.$client_fname.'</p>
            <p style="margin-top:0;margin-bottom:0;">You have declined the part-liquidation of your loan. <br>
            Your bulk payment will be effected as repayments within 24 working hours. <br>
            For any enquiries, contact hello@renmoney.com</p>
            <p style="margin-top:0;margin-bottom:0;"> <b>Thank you for choosing RenMoney MFB LTD.</b> </p>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
            <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
        </div>  
        ';
        $repayment_email_body = [
            "recipient" => [$client_email],
            "subject" => "New Repayment Schedule Rejected",
            "content" => $repayment_mail_content,
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
        $mail_content = '
        <div style="font-family: verdana, Trebuchet ms, arial; line-height: 1.5em">
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
            <p style="margin-top:0;margin-bottom:0;">Dear '.$client_fname.'</p>
            <p style="margin-top:0;margin-bottom:0;">This is your one time password'. $otp .' <br>
                if you did not initiate this process, please contact hello@renmoney.com
            </p>
            <p style="margin-top:0;margin-bottom:0;">Thank you for choosing RenMoney MFB LTD. </p>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
            <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
        </div>   
        ';
        $otp_mail_body = [
            "recipient" => [$client_email],
            "subject" => "Renmoney MFB LTD. One Time Password",
            "content" => $mail_content,
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