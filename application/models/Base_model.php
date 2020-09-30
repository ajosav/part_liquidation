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

    public function __construct()
    {
        parent::__construct();
        // $this->mambu_db = $this->load->database('mambu', TRUE);

        $this->load->config('renmoney');
        $this->load->database();

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
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $endpointURL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false, //added 
            CURLOPT_USERPWD => $this->username . ":" . $this->password,
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            return $response;
        }
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
		return $this->db->get($table_name, $condition)->result_array();
	}

    public function find($table, array $data) {
        $this->db->where($data);
        return $this->db->get($table)->row();
    }
    public function selectRepayment($schedule_id) {
        $query = "SELECT * FROM repayment_schedule WHERE schedule_id = '$schedule_id'";
        return $this->db->query($query)->result_array();
    }


    public function notifyMail($recipients = [], $subject, $body, $copy = [], $attachment = '') {
	
		if(empty($recipients) || $subject == '' || $body == '' ) {
			return false;
		}
		require(APPPATH.'third_party/PHPMailer-master/class.phpmailer.php'); 
		require(APPPATH.'third_party/PHPMailer-master/class.smtp.php');
		require(APPPATH.'third_party/PHPMailer-master/PHPMailerAutoload.php');
        
		$mail = new PHPMailer();

		$mail->isSMTP();                                      // Set mailer to use SMTP
		$mail->Host = 'pod51014.outlook.com';  // Specify main and backup SMTP servers
		$mail->SMTPAuth = true;                               // Enable SMTP authentication
		$mail->Username = 'portaluser@renmoneyng.com';                 // SMTP username
		$mail->Password = 'P@ssword1234567';                           // SMTP password
		$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
		$mail->Port = 587;                                    // TCP port to connect to

		$mail->setFrom('portaluser@renmoneyng.com', 'Backposting Process');

		$mail->addAddress($recipients[0]);
		// foreach($recipients as $recipient) {
		// 	$mail->addAddress($recipient); // Add a recipient
		// }
		
		if(!empty($copy)) {
			foreach($recipients as $recipient) {
				$mail->addCC($recipient);
			}
		}

		if($attachment != '') {
			$mail->addAttachment($attachment);
			// $mail->AddStringAttachment($pdfString, 'Demand Note Renewal'.$reportDate.'to'.$endDateReport.'.pdf');
		}
	
		$mail->isHTML(true);

		$mail->Subject = $subject; 
		$mail->Body    = $body; 
		$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
		//$this->email->send();
		if(!$mail->send()) {
			echo "Message could not be sent. \n";
			echo 'Mailer Error: ' . $mail->ErrorInfo . "\n";
		}
	}


}