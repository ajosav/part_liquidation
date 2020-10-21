<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base_model class.
 * 
 * @extends CI_Model
 */
class Base_model extends CI_Model {

    private $mambu_db;
    private $mambu_username;
    private $mambu_password;
    public $mambu_base_url;
    public $debug = false;

    private $renbroker_db;

    public function __construct()
    {
        parent::__construct();
        // $this->mambu_db = $this->load->database('mambu', TRUE);

        $this->load->config('renmoney');
        $this->load->database();
        $this->renbroker_db = $this->load->database('renbrokerstaging', TRUE);

        $this->mambu_base_url = $this->config->item('rnm_mambu_base_url');

        $this->mambu_username = $this->config->item('rnm_mambu_username');
        $this->mambu_password = $this->config->item('rnm_mambu_password');
    }


    public function dd($var) {
        echo "<pre>";
        die(var_dump($var));
        echo "</pre>";
    }

    /**
	 * calls a mambu api endpoint using get request
	 * 
	 * @access public
	 * @return array
     * @param enpointurl
	*/
    public function call_mambu_api_get($endpointURL) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointURL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERPWD, $this->mambu_username . ":" . $this->mambu_password);
        //remove the next line during deployment
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($this->debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
        }

        $output = curl_exec($ch);
        if ($this->debug) {
            if ($output === FALSE) {
                printf("cUrl error (#%d): %s<br>\n", curl_errno($ch), htmlspecialchars(curl_error($ch)));
            }

            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);

            echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
        }
        curl_close($ch);
        return $output;
    }

    /**
	 * calls a mambu api endpoint using post request
	 * 
	 * @access public
	 * @return array
     * @param enpointurl
     * @param arrayofparameters
	*/
    public function call_mambu_api($endpointURL, $arrayData) {

        $jsonData = json_encode($arrayData);
        //print_r($jsonData);
        //$endpointURL = $this->accountEndpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointURL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERPWD, $this->mambu_username . ":" . $this->mambu_password);
        //remove the next line during deployment
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($this->debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);

            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData))
        );

        $output = curl_exec($ch);
        if ($this->debug) {
            if ($output === FALSE) {
                printf("cUrl error (#%d): %s<br>\n", curl_errno($ch), htmlspecialchars(curl_error($ch)));
            }

            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);

            echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
        }

        curl_close($ch);

        return $output;
    }

    public function call_mambu_api_patch($endpointURL, $arrayData) {
        $data = json_encode($arrayData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpointURL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERPWD, $this->mambu_username . ":" . $this->mambu_password);
        //remove the next line during deployment
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");

        if ($this->debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);

            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data))
        );

        $output = curl_exec($ch);
        if ($this->debug) {
            if ($output === FALSE) {
                printf("cUrl error (#%d): %s<br>\n", curl_errno($ch), htmlspecialchars(curl_error($ch)));
            }

            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);

            echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
        }

        curl_close($ch);

        return $output;
        
    }

    public function get_repayments($loan_id) {
        $endpointURL = $this->mambu_base_url . "api/loans/" . $loan_id ."/repayments";
        return $this->call_mambu_api_get($endpointURL);
    }


    public function fetch_gl_accounts() {
        $endpointURL = $endpointURL = $this->mambu_base_url."api/transactionchannels";

        $response = $this->call_mambu_api_get($endpointURL);
        if ($response === FALSE) {
            return FALSE;
        }
        $gl_accounts = json_decode($response, TRUE);

        if(isset($gl_accounts['returnCode'])) {
            return false;
        }

        return $gl_accounts;
    }

    public function filter_gl_accounts($gl) {
        $customFields = $gl['customFields'];
        foreach($customFields as $fields) {
            if($fields['id'] == "Repayment_Method_Transactions") {
                return $gl;
            }
        }

        return false;
    }

    
	/**
	 * inserts multidimensional array of data to table
	 * 
	 * @access public
	 * @return boolean
	 * @param array
	 * @param string
	*/
	public function createBatch($table, $data) {
		return $this->db->insert_batch($table, $data);		
    }

    public function create($table, $data) {
        return $this->db->insert($table, $data);		
    }

    /**
	 * returna all data in a table
	 * 
	 * @access public
	 * @return boolean
	 * @param array
	 * @param string
	*/
    public function findAll($table_name) {
		return $this->db->get($table_name)->result_array();
    }
    
    public function findWhere($table_name, $condition) {
        $this->db->where($condition);
		return $this->db->get($table_name)->result_array();
		// return $this->db->get_compiled_select($table_name);
	}

    public function find($table, array $data) {
        $this->db->where($data);
        return $this->db->get($table)->row();
    }
    public function selectRepayment($schedule_id) {
        $query = "SELECT * FROM repayment_schedule WHERE schedule_id = '$schedule_id' AND principalDue != 0 ";
        return $this->db->query($query)->result_array();
    }

    public function update_table($table_name, $key, array $update_data = []) {
		$this->db->where($key);
		return $this->db->update($table_name, $update_data);
    }

    public function notifyMail($email_body = []) {
    
        $body = json_encode($email_body);     
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://prod.renmoney.com/emailapi/api/v1/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "RENMONEY-API-KEY: TGl2ZS1QYXNzd29yZDEyMzQ="
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;

    }
    
    public function sendSms($body) {
        $sms_body = json_encode($body);
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://renbroker.com/sms_system/api/Sms/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $sms_body,
        CURLOPT_HTTPHEADER => array(
            "RENMONEY-SMS-KEY: 4Opo57425A0dS7Y3o4y2P47Ww00g949B",
            "Content-Type: application/json"
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;

    }

    public function reduce_loan_tenure($repayments, $tenor) {
        $interest  = 0;
        $fees_due = 0;
        $penalty_due = 0;
        $principal_due = 0;
        foreach($repayments as $repayment) {
            $fees_due += $repayment['feesDue'];
            $interest += ($repayment['interestDue'] - $repayment['interestPaid']);
            $penalty_due += $repayment['penaltyDue'];
            $principal_due += $repayment['principalDue'];
        }

        $interest = $interest / $tenor;
        $fees_due = $fees_due / $tenor;
        $penalty_due = $penalty_due / $tenor;
        $principal_due = $principal_due / $tenor;
        $new_schedule = [];
        foreach($repayments as $index => $repayment) {
            if($index < $tenor) {
                $repayment['principalDue'] = $principal_due;
                $repayment['interestDue'] = $interest;
                $repayment['feesDue'] = $fees_due;
                $repayment['penaltyDue'] = $penalty_due;
                $new_schedule[] = $repayment;
            } else {
                $repayment['principalDue'] = 0;
                $repayment['interestDue'] = 0;
                $repayment['feesDue'] = 0;
                $repayment['penaltyDue'] = 0;
                $new_schedule[] = $repayment;
            }
            
        }

        return $new_schedule;
    }

    public function selectAllFromRemita($table_name) {
        return $this->renbroker_db->get($table_name)->result_array();
    }

    public function findWhereRemita($table_name,$condition) {
        $this->renbroker_db->where($condition);
		return $this->renbroker_db->get($table_name)->result_array();
    }

    public function updateRemitaRepayments($table_name, $condition, array $update_data = []) {
		$this->renbroker_db->where($condition);
		return $this->renbroker_db->update($table_name, $update_data);
    }

}