<?php

if (!defined('SAFE_INC'))
    die ("Hacking attempt...");

include_once SOURCEDIR . '/includes/klassen/EroCloudMagicLink.inc.php';
include_once SOURCEDIR . '/includes/klassen/EroCloudNotificationCenter.inc.php';
include_once SOURCEDIR . '/includes/klassen/EroCloudMailer.inc.php';

/**
 * SendSMTPmail - Abwärtskompatibler Wrapper auf die globale Klasse EroCloudMailer.
 */
class SendSMTPmail
{
    public $add_addr = '';
    public $add_name = '';
    public $subject = '';
    public $message = '';
    public $isHTML = true;
    public $addAttachment_path = '';
    public $addAttachment_name = '';
    public $addAttachment = [];
    public $addEmbeddedImage = [];
    public $addEmbeddedImage_name = '';

    public function __construct()
    {
    }

    public function add($addr, $name = '')
    {
        $this->add_addr = (string)$addr;
        $this->add_name = (string)$name;
    }

    public function subject($subject)
    {
        $this->subject = (string)$subject;
    }

    public function message($message)
    {
        $this->message = (string)$message;
    }

    public function addAttachment($attachment_path, $attachment_name = '')
    {
        $this->addAttachment_path = (string)$attachment_path;
        $this->addAttachment_name = (string)$attachment_name;
    }

    public function addAttachment2($attachment_ary)
    {
        $this->addAttachment = is_array($attachment_ary) ? $attachment_ary : [];
    }

    public function isHTML($var)
    {
        $this->isHTML = (bool)$var;
    }

    public function addEmbeddedImage($images_ary)
    {
        $this->addEmbeddedImage = is_array($images_ary) ? $images_ary : [];
    }

    public function send()
    {
        $mailer = new EroCloudMailer();
        $attachments = [];

        if ($this->addAttachment_path !== '') {
            $attachments[] = [$this->addAttachment_path, $this->addAttachment_name];
        }
        if (is_array($this->addAttachment) && count($this->addAttachment) > 0) {
            foreach ($this->addAttachment as $item) {
                $attachments[] = $item;
            }
        }

        $ok = $mailer->send(
            $this->add_addr,
            $this->add_name,
            $this->subject,
            $this->message,
            '',
            ['attachments' => $attachments]
        );

        if (!$ok) {
            return $mailer->lastError;
        }

        return true;
    }
}
