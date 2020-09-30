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

   

    public function index() {
         
        $this->load->view('part_liquidation/meta_link');
        $this->load->view('part_liquidation/client/preview_schedule'); 
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