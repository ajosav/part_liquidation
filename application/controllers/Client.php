<?php

defined('BASEPATH') OR exit('No direct script access allowed');




class Client  extends CI_Controller {
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

    }


    public function repayment_schedule() {
        
    }


    public function verify_otp() {

    }

    public function view_otp() {
        $this->load->view('part_liquidation/meta_link');
		$this->load->view('part_liquidation/otp_verification'); 
		$this->load->view('part_liquidation/footer_link');
    }

}