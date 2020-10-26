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
        return $this->output
        ->set_content_type('application/json')
        ->set_status_header(400)
        ->set_output(
            json_encode($this->input->post())
        );
        // $this->Base_model->dd($this->input->post());
        $reason = $this->input->post('rejection_reason');
        $schedule_id = $this->input->post('schedule_id');
        $rejection_state = $this->input->post('rejection_state');
        $client_fname = $this->input->post('client_fname');
        $client_lname = $this->input->post('client_lname');
        $client_email = $this->input->post('client_email');
        $client_phone = $this->input->post('client_phone');
        $bulk_amount = $this->input->post('liquidation_amount');
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
                Details: '.$reason.'<br>
                Loan ID: '.$loan_id.'<br>
                Bulk Amount: '.$bulk_amount.'

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
        // '.$client_fname.'
        $refund_mail_content ='
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
                                                            <span style="line-height: 34px; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 24px; font-weight: 700; letter-spacing: -0.4px; color: #ffffff">New Repayment Schedule Rejected  </span>
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
                                                    <td class="pc-fb-font" style="line-height: 28px; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 300; letter-spacing: -0.2px; color: #9B9B9B" valign="top"><span style="color: #686868;">Hello '.$client_fname.',</span><br><br><span style="color: #686868;">You have declined the part-liquidation of your loan.&nbsp;&nbsp;</span><br><span style="color: #686868;">Your bulk payment will be refunded within 48 working hours.</span><br><br><span style="color: #686868;">If you need more information, please email <span style="color: #015f82;">hello@renmoney.com</span> or call us at 0700 5000 500; our phone lines are open from 7am to 11pm from Mondays to Saturdays and 8am to 5pm on Sundays.&nbsp;</span><br><br><strong><span style="color: #015f82;">The Renmoney Team</span></strong></td>
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
        
        $refund_email_body = [
            "recipient" => [$client_email],
            "subject" => "New Repayment Schedule Rejected",
            "content" => $refund_mail_content,
            "cc" => $this->cc,
            "category" => ['Part-liquidation']
        ];

        $repayment_mail_content = '
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
                                                            <span style="line-height: 34px; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 24px; font-weight: 700; letter-spacing: -0.4px; color: #ffffff">New Repayment Schedule Rejected  </span>
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
                                                    <td class="pc-fb-font" style="line-height: 28px; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 300; letter-spacing: -0.2px; color: #9B9B9B" valign="top"><span style="color: #686868;">Hello  '.$client_fname.',</span><br><br><span style="color: #686868;">You have declined the part-liquidation of your loan.&nbsp;&nbsp;</span><br><span style="color: #686868;">Your bulk payment will be effected as repayments within 24 working hours.</span><span style="color: #686868;"><span style="color: #015f82;">&nbsp;</span></span><br><br><span style="color: #686868;">If you need more information, please email <span style="color: #015f82;">hello@renmoney.com</span> or call us at 0700 5000 500; our phone lines are open from 7am to 11pm from Mondays to Saturdays and 8am to 5pm on Sundays.&nbsp;</span><br><br><strong><span style="color: #015f82;">The Renmoney Team</span></strong></td>
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
                "notes" => "BEING late instalment repayment of Bulk amount $loan_schedule->liquidationAmount. TransactionDate: $loan_schedule->transactionDate. $loan_schedule->comment"
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
                    "notes" => "BEING Part-Liquidation of Bulk amount $loan_schedule->liquidationAmount. TransactionDate: $loan_schedule->transactionDate. $loan_schedule->comment",
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
                    "notes" => "BEING Part-Liquidation of Bulk amount $loan_schedule->liquidationAmount. TransactionDate: $loan_schedule->transactionDate. $loan_schedule->comment"
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
                    "notes" => "BEING Part-Liquidation of Bulk amount $loan_schedule->liquidationAmount. TransactionDate: $loan_schedule->transactionDate. $loan_schedule->comment",
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
        if($this->find_remita_direct_debit($loan_id)) {
            $this->update_remita_schedule($loan_id);
        }
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
                                                            <span style="line-height: 34px; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 24px; font-weight: 700; letter-spacing: -0.4px; color: #ffffff">Repayment Schedule Updated  </span>
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
                                                    <td class="pc-fb-font" style="line-height: 28px; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 300; letter-spacing: -0.2px; color: #9B9B9B" valign="top"><span style="color: #686868;">Hello '. $client_fname .',</span><br><br><span style="color: #686868;">Please note that the New repayment schedule is now active.<br>&nbsp; </span><span style="color: #686868;">&nbsp;&nbsp;</span><br><span style="color: #686868;">If you need more information, please email <span style="color: #015f82;">hello@renmoney.com</span> or call us at 0700 5000 500; our phone lines are open from 7am to 11pm from Mondays to Saturdays and 8am to 5pm on Sundays.&nbsp;</span><br><br><span style="color: #015f82;"><strong>The Renmoney Team</strong></span></td>
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
                                                        <td class="pc-fb-font" style="line-height: 28px; font-family: Helvetica, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 300; letter-spacing: -0.2px; color: #9B9B9B" valign="top"><span style="color: #555555;">Hello '.$client_fname.',</span><br><span style="color: #555555;">&nbsp;</span><br><span style="color: #555555;">This is your one time password '. $otp .' <br>&nbsp;<br>If you did not initiate this process, please contact <span style="color: #015f82;">hello@renmoney.com</span>&nbsp;<br>Thank you for choosing Renmoney MFB LTD.&nbsp;<br><br><strong><span style="color: #015f82;">The Renmoney Team&nbsp;</span></strong> </span>
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

    private function update_remita_schedule($loan_id) {
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

    private function find_remita_direct_debit($loan_id) {
        $remita_debit = false;
        $endpointURL = $this->mambu_base_url."api/loans/{$loan_id}?fullDetails=true";
        $loanDetails = json_decode($this->Base_model->call_mambu_api_get($endpointURL), TRUE);
        $customFields = $loanDetails['customFieldValues'];

        foreach($customFields as $customField) {
            $field = $customField['customField'];
            if($field['id'] == 'Repayment_Method_Loan_Accounts') {
                $selectOptions = $field['customFieldSelectionOptions'];
                foreach($selectOptions as $option) {
                    if(in_array('Direct Debit (Remita)', $option)) {
                        $remita_debit = true;
                        break;
                    }
                }
            }
            

        }

        return $remita_debit;
    }
 
}