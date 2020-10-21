<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Client extends CI_Controller {

    public $mambu_base_url;
    private $today;
    private $team_mail;
    private $cc;
	public function __construct() {
		
        parent::__construct();
        
        $this->team_mail = ["eobukohwo@renmoney.com"];
        $this->cc = ["jadebayo@renmoney.com"];
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

    public function index() {
        // $rate = 4.740; $nper = 12; $pv = 500000.00; $fv = 0; $type = 0; $fee_rate = (0.01 / 100);
        // $rate = 11.83/4.3; $nper = 6; $pv =  219584.07; $fv = 0; $type = 0; $fee_rate = (0.00 / 100);

        // $schedule = $this->calculateSchedule($rate, $nper, $pv, $fv, $type, $fee_rate);

        // $this->Base_model->dd($schedule);
        
    }

    function calculateSchedule($rate, $nper, $pv, $fv, $type, $fee_rate) {
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

   
    public function loan_schedule($url = "") {
        if(base64_encode(base64_decode($url)) != $url) {
            $this->load->view('part_liquidation/client/client_link_error'); 
            return false;
        }
        
        $link = json_decode(base64_decode($url));

        
        
        $schedule_id = $link->schedule_id;
        $valid_till = $link->valid_till;

        // $valid_till = "2020-10-03";

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
        
        $status = 3;

        $data = [
            'status' => $status
        ];

        $reject_state_prefix = $rejection_state == "refund" ? '' : "Apply as";

        $team_mail_body = '
        <div style="font-family: verdana, Trebuchet ms, arial;">
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
                <p>Dear Team</p>
                <p>Please note that '.$client_fname. ' '. $client_lname .' has rejected the New repayment schedule. </br>
                Reason: '.$reject_state_prefix.' '.$rejection_state.'<br>
                Details: '.$reason.'

                <p>
                <b>Best Regards, <br>
                The Renmoney Team </b>
                </p>

            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
            <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
        </div>';

        $team_email_body = [
            "recipient" => $this->team_mail,
            "subject" => "Rejected Repayment Schedule",
            "content" => $team_mail_body,
            "cc" => $this->cc,
            "category" => ['Part-liquidation']
        ];

        $refund_mail_content ='
        <div style="font-family: verdana, Trebuchet ms, arial;">
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
                <p>Dear '.$client_fname.'</p>
                <p>You have declined the new schedule of your loan. <br>
                Your bulk payment will be refunded within 24 working hours. <br>
                For any enquiries, contact hello@renmoney.com</p>

                <p><b>Thank you for choosing RenMoney MFB LTD. </b></p>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
            <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
        </div>';
        $refund_email_body = [
            "recipient" => [$client_email],
            "subject" => "New Repayment Schedule Rejected",
            "content" => $refund_mail_content,
            "cc" => $this->cc,
            "category" => ['Part-liquidation']
        ];

        $repayment_mail_content = '
        <div style="font-family: verdana, Trebuchet ms, arial;">
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
            <p>Dear '.$client_fname.'</p>
            <p>You have declined the new schedule of your loan. <br>
            Your bulk payment will be effected as repayments within 24 working hours. <br>
            For any enquiries, contact hello@renmoney.com</p>
            <p> <b>Thank you for choosing RenMoney MFB LTD.</b> </p>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
            <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
        </div>  
        ';
        $repayment_email_body = [
            "recipient" => [$client_email],
            "subject" => "New Repayment Schedule Rejected",
            "content" => $repayment_mail_content,
            "cc" => $this->cc,
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
                    json_encode('Liquidation Schedule Successfully Declined')
                );
            }
            
        return $this->output
        ->set_content_type('application/json')
        ->set_status_header(400)
        ->set_output(
            json_encode('Schedule could not be declined for some reason')
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
        $loanDetails = $this->mambu_base_url."api/loans/{$loan_id}?fullDetails=true";
        $loanAccount = json_decode($this->Base_model->call_mambu_api_get($loanDetails), TRUE);
        $total_interest_due = 0;
        $repayments_interest_due = $this->get_outstanding_repayments($loan_id);
        foreach($repayments_interest_due as $repayment) {
            $total_interest_due += ($repayment->interestDue - $repayment->interestPaid);
        }

              
        $data = [
            "schedule_id" => $schedule_id,
            "client_fname" => $client_fname,
            "client_lname" => $client_lname,
            "client_email" => $client_email,
            "phone" => $this->maskPhoneNumber($client_phone),
            "loan_id" => $loan_id,
            "loanDetails" => $loanAccount,
            "totalInterestDue" => $total_interest_due
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
        $total_interest_due = $this->input->post('totalInterestDue');
        $principalBal = $this->input->post('principalBal');
        $mambu_principal_due = $this->input->post('principalDue');
        $penaltyBal = $this->input->post('penaltyBal');
        $feesBal = $this->input->post('feesBal');
        $loan_amount = $this->input->post('loan_amount');
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
            $this->Base_model->update_table('loan_schedule', ['schedule_id' => $schedule_id], ['status' => 5]);
            $team_mail_body = '
                <div style="font-family: verdana, Trebuchet ms, arial; line-height: 1.5em>
                    <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
                        <p>Hello Team, </p>
                        <p>Please note that '. $client_fname . ' '. $client_lname .' with loanID ' . $loan_id.' has accepted the new repayment schedule for their Part Liquidation request </p>
                        
                        <p>Please follow up with client\'s payment and notify Operations team to Effect the New schedule as this is only valid for 24 hours </p>

                        <p>
                        <b>Best Regards, <br>
                        The Renmoney Team </b>
                        </p>

                    <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
                    <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
                </div>';

                $team_email_body = [
                    "recipient" => $this->team_mail,
                    "subject" => "Accepted Repayment Schedule",
                    "content" => $team_mail_body,
                    "cc" => $this->cc,
                    "category" => ['Part-liquidation']
                ];

                $this->Base_model->notifyMail($team_email_body);
                    return $this->output
                    ->set_content_type('application/json')
                    ->set_status_header(200)
                    ->set_output(
                        json_encode('Your Part liquidation is queued, please proceed to payment')
                    );
                    
        }

        $repayments = $this->Base_model->findWhere('repayment_schedule', ['schedule_id' => $schedule_id]);

        $has_late_repayment = $this->get_late_repayments($loan_id);

        $repayment_collections = [];

        $number_of_late_installments = count($has_late_repayment);
    
        foreach($repayments as $index => $repayment) {
            if($index == 0){
                $repayment_collections[] = [
                    "encodedKey" => $repayment['encodedKey'],
                    "principalDue" => number_format((float) $loan_schedule->reducedPrincipal, 2, '.', ''),
                    "interestDue" => number_format((float) $repayment['interestDue'], 2, '.', ''),
                    "feesDue" => $repayment['feesDue'],
                    "penaltyDue" => $repayment['penaltyDue'],
                    "parentAccountKey" => $repayment['parentAccountKey'],
                ];
            } else {
                $repayment_collections[] = [
                    "encodedKey" => $repayment['encodedKey'],
                    "principalDue" => number_format((float) $repayment['principalDue'], 2, '.', ''),
                    "interestDue" => number_format((float) $repayment['interestDue'], 2, '.', ''),
                    "feesDue" => number_format((float) $repayment['feesDue'], 2, '.', ''),
                    "penaltyDue" => number_format((float) $repayment['penaltyDue'], 2, '.', ''),
                    "parentAccountKey" => $repayment['parentAccountKey'],
                ];
            }
           
        }
        

        $principal_sum = array_sum(array_column($repayment_collections, 'principalDue'));
        $interest_sum = array_sum(array_column($repayment_collections, 'interestDue'));
        $fees_sum = array_sum(array_column($repayment_collections, 'feesDue'));
        $penalty_sum = array_sum(array_column($repayment_collections, 'penaltyDue'));

        // $newInterest =  ((float)$total_interest_due - $interest_sum);
        $newPrincipal = ((float) $principalBal - ($principal_sum + $mambu_principal_due));
        $newFees = ((float) $feesBal - $fees_sum);
        $newPenalty = ((float) $penaltyBal - $penalty_sum);

        $collect_repayment = [];
        foreach($repayments as $index => $repayment) {
            if($index == 0){
                $patch_principal = $loan_schedule->reducedPrincipal + $newPrincipal;
                $collect_repayment['repayments'][] = [
                    "encodedKey" => $repayment['encodedKey'],
                    "principalDue" =>  number_format((float) $patch_principal, 2, '.', ''),
                    "interestDue" =>  number_format((float) $repayment['interestDue'], 2, '.', ''),
                    "feesDue" => $repayment['feesDue'],
                    "penaltyDue" => $repayment['penaltyDue'],
                    "parentAccountKey" => $repayment['parentAccountKey'],
                ];
            }  else {
                $collect_repayment['repayments'][] = [
                    "encodedKey" => $repayment['encodedKey'],
                    "principalDue" => number_format((float) $repayment['principalDue'], 2, '.', ''),
                    "interestDue" => number_format((float) $repayment['interestDue'], 2, '.', ''),
                    "feesDue" => number_format((float) $repayment['feesDue'], 2, '.', ''),
                    "penaltyDue" => number_format((float) $repayment['penaltyDue'], 2, '.', ''),
                    "parentAccountKey" => $repayment['parentAccountKey'],
                ];
            }
           
        }

        
        if($loan_schedule->outstandingBalance > 0) {
            $repayment_data = [
                "type" => "REPAYMENT",
                "amount" => round($loan_schedule->outstandingBalance, 2),
                "date" => date('Y-m-d'),
                "notes" => "BEING late instalment repayment of Bulk amount $loan_schedule->liquidationAmount. TransactionDate: $loan_schedule->transactionDate"
            ];
            $data = [
                "message" => "Entering late repayments for loan {$loan_id}",
                "details" => json_encode($repayment_data),
                "schedule_id" => $schedule_id,
                "loan_id" => $loan_id,
                "date" => date('Y-m-d H:i:s')
            ];
            $this->Base_model->create('liquidation_log', $data);

            $response = json_decode($this->Base_model->call_mambu_api($transaction_url, $repayment_data), TRUE);

            if(isset($response['returnCode'])) {
                $data = [
                    "message" => "Failed to pay late instalments {$loan_id}",
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
                    json_encode($response['returnStatus'])
                );
            }

            $data = [
                "message" => "Late installments paid off",
                "details" => json_encode($response),
                "schedule_id" => $schedule_id,
                "loan_id" => $loan_id,
                "date" => date('Y-m-d H:i:s')
            ];
            $this->Base_model->create('liquidation_log', $data);

        }

        $reschedule_url = $this->mambu_base_url."api/loans/{$loan_id}/repayments";

        $data = [
            "message" => "rescheduling loan {$loan_id}",
            "details" => json_encode($collect_repayment),
            "schedule_id" => $schedule_id,
            "loan_id" => $loan_id,
            "date" => date('Y-m-d H:i:s')
        ];
       
        $this->Base_model->create('liquidation_log', $data);
        $response = json_decode($this->Base_model->call_mambu_api_patch($reschedule_url, $collect_repayment), TRUE);

        if(isset($response['returnCode'])) {
            $data = [
                "message" => "Failed to liquidate account",
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
                json_encode($response['returnStatus'])
            );
        }
        $data = [
            "message" => "Loan Account Rescheduling was successful",
            "details" => json_encode($response),
            "schedule_id" => $schedule_id,
            "loan_id" => $loan_id,
            "date" => date('Y-m-d H:i:s')
        ];
        $this->Base_model->create('liquidation_log', $data);


        if($loan_schedule->interestBalance > 0) {
            $repayment_data = '';
            if($loan_schedule->transaction_method != '') {
                $repayment_data = [
                    "type" => "REPAYMENT",
                    "amount" => round($loan_schedule->interestBalance, 2),
                    "date" => date('Y-m-d'),
                    "method" => $loan_schedule->transactionChannel,
                    "notes" => "BEING Part-Liquidation of Bulk amount $loan_schedule->liquidationAmount. TransactionDate: $loan_schedule->transactionDate",
                    "customInformation" => [
                        [
                            "value" => $loan_schedule->transaction_method,
                            "customFieldID" => "Repayment_Method_Transactions"
                        ]
                    ]
                ];
            } else {
                $repayment_data = [
                    "type" => "REPAYMENT",
                    "amount" => round($loan_schedule->interestBalance, 2),
                    "date" => date('Y-m-d'),
                    "notes" => "BEING Part-Liquidation of Bulk amount $loan_schedule->liquidationAmount. TransactionDate: $loan_schedule->transactionDate"
                ];
            }
             $data = [
                "message" => "Entering Repayment for loan {$loan_id}",
                "details" => json_encode($repayment_data),
                "schedule_id" => $schedule_id,
                "loan_id" => $loan_id,
                "date" => date('Y-m-d H:i:s')
            ];
       
            $this->Base_model->create('liquidation_log', $data);
            $response = json_decode($this->Base_model->call_mambu_api($transaction_url, $repayment_data), TRUE);
            
            if(isset($response['returnCode'])) {
                $repayment_data = [
                    "type" => "REPAYMENT",
                    "amount" => round($loan_schedule->interestBalance, 2),
                    "date" => date('Y-m-d'),
                    "notes" => "BEING Part-Liquidation of Bulk amount $loan_schedule->liquidationAmount. TransactionDate: $loan_schedule->transactionDate",
                ];
                $response = json_decode($this->Base_model->call_mambu_api($transaction_url, $repayment_data), TRUE);
                if(isset($response['returnCode'])) {
                    $data = [
                        "message" => "Failed to pay Liquiation Balance",
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
                        json_encode($response['returnStatus'])
                    );
                }


            }

            $data = [
                "message" => "Bulk Repayments settled",
                "details" => json_encode($response),
                "schedule_id" => $schedule_id,
                "loan_id" => $loan_id,
                "date" => date('Y-m-d H:i:s')
            ];
            $this->Base_model->create('liquidation_log', $data);

        }

        $this->Base_model->update_table('loan_schedule', ['schedule_id' => $schedule_id], ['status' => 5]);
        $team_mail_body = '
            <div style="font-family: verdana, Trebuchet ms, arial; line-height: 1.5em>
                <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
                    <p>Hello Team, </p>
                    <p>Please note that '. $client_fname .' has accepted has accepted the New repayment schedule based on their recent bulk payment N' . round($loan_schedule->liquidationAmount, 2) . ' for account '. $loan_id .'</p>
                    
                    <p>The New repayment schedule is now Active </p>

                    <p>
                    <b>Best Regards, <br>
                    The Renmoney Team </b>
                    </p>

                <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
                <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
                </div>';
        $client_mail_body = '
            <div style="font-family: verdana, Trebuchet ms, arial; line-height: 1.5em>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-top.png"> </p>
                <p>Dear '. $client_fname .', </p>
                <p>Please note that the New repayment schedule is now Active. For any enquiries, contact hello@renmoney.com</p>
            <p style="margin-top:0;margin-bottom:0;"><img data-imagetype="External" src="https://renbrokerstaging.com/images/uploads/email-template-bottom.png"> </p>
            <img data-imagetype="External" src="/actions/ei?u=http%3A%2F%2Furl7993.renmoney.com%2Fwf%2Fopen%3Fupn%3D41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D&amp;d=2020-10-02T05%3A34%3A50.506Z" originalsrc="http://url7993.renmoney.com/wf/open?upn=41xtn7k-2FRcosoYn6DwxG1-2BXTfUybVa4h7edFGl3JAG-2F-2FfqJLVBPnMU1KMUstVhJfuERqIIzADTZgE0jA-2FnIsyj65PZrWnoC-2F4r4iU2kB4ri4hITKh3uMah6-2BHGwEhXS4CLUjlXvp59bymbhMdWiZCn8yINjGinxUBSWwnHZku5D80FJoXPwZ2M05Oq8Y2mfNHdlSSLAqkDip4yTSS2Ee3A2QbWkHl6qj0VfZhHWWIRqszcPZ80C6G7WhGrChD4n8UXYkpRltYwI6A2BXYORTB1c0isOG3fStIRwIG1EXFfc-3D" data-connectorsauthtoken="1" data-imageproxyendpoint="/actions/ei" data-imageproxyid="" style="width:1px;height:1px;margin:0;padding:0;border-width:0;" border="0">
            </div>';

        $team_email_body = [
            "recipient" => $this->team_mail,
            "subject" => "Accepted Repayment Schedule",
            "content" => $team_mail_body,
            "cc" => $this->cc,
            "category" => ['Part-liquidation']
        ];

        $client_email_body = [
            "recipient" => $client_email,
            "subject" => "Repayment Schedule Updated",
            "content" => $client_mail_body,
            "cc" => $this->cc,
            "category" => ['Part-liquidation']
        ];

        
        $this->Base_model->notifyMail($team_email_body);
        $this->Base_model->notifyMail($client_email_body);

        return $this->output
        ->set_content_type('application/json')
        ->set_status_header(200)
        ->set_output(
            json_encode("Loan account liquidation was successful")
        );
        
        // $this->Base_model->

       
    }

    private function send_otp($client_phone, $client_email, $client_fname) {
        // $client_email = "jadebayo@renmoney.com";
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
            <p style="margin-top:0;margin-bottom:0;">This is your one time password '. $otp .' <br>
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

    private function get_late_repayments($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id));
        $available_tenor = [];
        foreach($repayments as $index => $repayment) {
            if((string) $repayment->state == "LATE") {
                $available_tenor[] = $repayment;
            }
        }

        return $available_tenor;
    }
    private function get_first_installment_position($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id));
        foreach($repayments as $index => $repayment) {
            if((string) $repayment->state == "PENDING") {
               return $index + 1;
            }
        }

    }

    public function update_remita_schedule($loan_id) {
        $repayments = json_decode($this->Base_model->get_repayments($loan_id), TRUE);
        foreach($repayments as $repayment) {
            $status = '';
            if($repayment['state'] == 'GRACE') {
                $status = "CANCELLED";
            } elseif($repayment['state'] == 'PARTIALLY_PAID') {
                $status = "PART_PAID";
            } elseif($repayment['state'] == 'PAID') {
                $status = "PAID";
            } elseif($repayment['state'] == 'LATE') {
                $status = "PENDING";
            } else {
                $status = $repayment['state'];
            }

            if($status == "PENDING") {
                $remita_update = [
                    'principal_due' => $repayment['principalDue'],
                    'interest_due' => $repayment['interestDue'],
                    'total_due' => $repayment['principalDue'] + $repayment['interestDue'],
                    'status' => $status
                ];
                $this->Base_model->updateRemitaRepayments('repayments', ['mambu_loan_id' => $loan_id, 'mambu_encodedkey' => $repayment['encodedKey']], $remita_update);
            } else {
                $this->Base_model->updateRemitaRepayments('repayments', ['mambu_loan_id' => $loan_id, 'mambu_encodedkey' => $repayment['encodedKey']], ['status' => $status]);
            }
        }
    }

}