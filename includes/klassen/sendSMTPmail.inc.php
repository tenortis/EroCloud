<?php


class SendSMTPmail {
    
    var $add_addr;
    var $add_name;
    var $subject;
    var $message;
    var $isHTML;
    var $addAttachment_path;
    var $addAttachment_name;
    var $addAttachment;
    var $addEmbeddedImage;
    var $addEmbeddedImage_name;
    
    public function add($addr, $name='') {
        $this->add_addr = $addr;
        $this->add_name = $name; 
    }
    
    public function subject($subject) {
        $this->subject = $subject;
    }
    
    public function message($message) {
        $this->message = $message;
    }
    
    public function addAttachment($attachment_path, $attachment_name) {
        $this->addAttachment_path = $attachment_path;
        $this->addAttachment_name = $attachment_name;
    }
    
    public function addAttachment2($attachment_ary) {
        $this->addAttachment = $attachment_ary;
    }

    public function isHTML($var) {
        $this->isHTML = $var;
    }
    
    public function addEmbeddedImage($images_ary) {
        $this->addEmbeddedImage = $images_ary;
    }
        
    public function __construct() {

    }
        
    public function send() {
    	include_once(SOURCEDIR.'/includes/klassen/phpmailer/class.phpmailer.php');
        include_once(SOURCEDIR.'/includes/klassen/phpmailer/class.smtp.php');
        include_once(SOURCEDIR.'/includes/klassen/phpmailer/phpmailer.lang-de.php');
   
    	$mail = new PHPMailer();
    
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->IsSMTP(); // telling the class to use SMTP
        $mail->SMTPDebug  = 0;                     // enables SMTP debug information (for testing)
                                                   // 1 = errors and messages
                                                   // 2 = messages only
        $mail->SMTPAuth   = true;                  // enable SMTP authentication
        $mail->SMTPSecure = "tls";                 // sets the prefix to the servier
        $mail->Host       = "mail.pay4coins.com";  // SMTP server     
        $mail->Port       = 587;                   
        $mail->Username   = "support@pay4coins.com"; 
        $mail->Password   = "croboshZetVoos9";            
        
        $mail->SetFrom('support@pay4coins.com', 'Pay4Coins.com');
        $mail->AddReplyTo("support@pay4coins.com","Pay4Coins.com");
        
        if (!empty($this->add_name)) {
            $mail->AddAddress($this->add_addr, $this->add_name);
        } else {
            $mail->AddAddress($this->add_addr);
        }
    
    	$mail->Subject    = $this->subject;
    	$mail->AltBody    = "Um die E-Mail lesen zu können, verwenden Sie bitte ein HTML kompatiblen E-Mail-Viewer!";
    	
        $mail->AddEmbeddedImage(SOURCEDIR.'/api/images/pay4coins_250x49.png', 'logo', 'pay4coins.png');
        $mail->AddEmbeddedImage(SOURCEDIR.'/api/images/cipamedia_300x34.png', 'logo2', 'cipamedia.png');

        $mail->MsgHTML($this->message);
       
        if (is_array($this->addEmbeddedImage) AND count($this->addEmbeddedImage) >= 1) {
            foreach($this->addEmbeddedImage as $value) {
                $mail->addEmbeddedImage($value[0], $value[1], $value[2]);
            }
        }
        
        #$mail->AddAttachment($this->addAttachment_path, $this->addAttachment_name);
        if (is_array($this->addAttachment) AND count($this->addAttachment) >= 1) {
            foreach($this->addAttachment as $value) {
                $mail->AddAttachment($value[0], $value[1]);
            }
        }
    
    	if(!$mail->Send()) {
    		return $mail->ErrorInfo;
    	} else {
    		return true;
    	}        
            
        $mail->ClearAddresses();
        $mail->ClearAttachments();
    }
}

  



?>
