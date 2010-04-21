<?php
/**
 * FormIt
 *
 * Copyright 2009-2010 by Shaun McCormick <shaun@collabpad.com>
 *
 * FormIt is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * FormIt is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * FormIt; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * @package formit
 */
/**
 * Base Hooks handling class
 *
 * @package formit
 */
class fiHooks {
    /**
     * @var array $errors A collection of all the processed errors so far.
     * @access public
     */
    public $errors = array();
    /**
     * @var array $hooks A collection of all the processed hooks so far.
     * @access public
     */
    public $hooks = array();
    /**
     * @var modX $modx A reference to the modX instance.
     * @access public
     */
    public $modx = null;
    /**
     * @var FormIt $formit A reference to the FormIt instance.
     * @access public
     */
    public $formit = null;

    /**
     * The constructor for the fiHooks class
     *
     * @param FormIt &$formit A reference to the FormIt class instance.
     * @param array $config Optional. An array of configuration parameters.
     * @return fiHooks
     */
    function __construct(FormIt &$formit,array $config = array()) {
        $this->formit =& $formit;
        $this->modx =& $formit->modx;
        $this->config = array_merge(array(
        ),$config);
    }

    /**
     * Loads an array of hooks. If one fails, will not proceed.
     *
     * @access public
     * @param array $hooks The hooks to run.
     * @parma array $fields The fields and values of the form
     * @return array An array of field name => value pairs.
     */
    public function loadMultiple($hooks,$fields) {
        if (empty($hooks)) return array();
        if (is_string($hooks)) $hooks = explode(',',$hooks);

        $this->hooks = array();
        $this->fields =& $fields;
        foreach ($hooks as $hook) {
            $success = $this->load($hook,$this->fields);
            if (!$success) return $this->hooks;
            /* dont proceed if hook fails */
        }
        return $this->hooks;
    }

    /**
     * Load a hook. Stores any errors for the hook to $this->errors.
     *
     * @access public
     * @param string $hook The name of the hook. May be a Snippet name.
     * @param array $fields The fields and values of the form.
     * @return boolean True if hook was successful.
     */
    public function load($hook,$fields = array()) {
        $success = false;
        $this->hooks[] = $hook;

        if (method_exists($this,$hook) && $hook != 'load') {
            /* built-in hooks */
            $success = $this->$hook($fields);

        } else if ($snippet = $this->modx->getObject('modSnippet',array('name' => $hook))) {
            /* custom snippet hook */
            $properties = $this->formit->config;
            $properties['hook'] =& $this;
            $properties['fields'] = $fields;
            $success = $snippet->process($properties);

        } else {
            /* no hook found */
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[FormIt] Could not find hook "'.$hook.'".');
            $success = true;
        }

        if (is_array($success) && !empty($success)) {
            $this->errors = array_merge($this->errors,$success);
            $success = false;
        } else if ($success != true) {
            $this->errors[$hook] .= ' '.$success;
            $success = false;
        }
        return $success;
    }

    /**
     * Gets the error messages compiled into a single string.
     *
     * @access public
     * @param string $delim The delimiter between each message.
     * @return string The concatenated error message
     */
    public function getErrorMessage($delim = "\n") {
        return implode($delim,$this->errors);
    }

    /**
     * Redirect to a specified URL.
     *
     * Properties needed:
     * - redirectTo - the ID of the Resource to redirect to.
     *
     * @param array $fields An array of cleaned POST fields
     * @return boolean False if unsuccessful.
     */
    public function redirect(array $fields = array()) {
        if (empty($this->formit->config['redirectTo'])) return false;

        $url = $this->modx->makeUrl($this->formit->config['redirectTo']);
        return $this->modx->sendRedirect($url);
    }

    /**
     * Send an email of the form.
     *
     * Properties:
     * - emailTpl - The chunk name of the chunk that will be the email template.
     * This will send the values of the form as placeholders.
     * - emailTo - A comma separated list of email addresses to send to
     * - emailToName - A comma separated list of names to pair with addresses.
     * - emailFrom - The From: email address. Defaults to either the email
     * field or the emailsender setting.
     * - emailFromName - The name of the From: user.
     * - emailSubject - The subject of the email.
     * - emailHtml - Boolean, if true, email will be in HTML mode.
     *
     * @access public
     * @param array $fields An array of cleaned POST fields
     * @return boolean True if email was successfully sent.
     */
    public function email(array $fields = array()) {
        $tpl = $this->modx->getOption('emailTpl',$this->formit->config,'');

        $emailFrom = empty($fields['email']) ? $this->modx->getOption('emailsender') : $fields['email'];
        if (empty($emailFrom)) {
            $emailFrom = $this->modx->getOption('emailFrom',$this->formit->config,$emailFrom);
        }
        $emailFromName = $this->modx->getOption('emailFromName',$this->formit->config,$emailFrom);
        $emailHtml = $this->modx->getOption('emailHtml',$this->formit->config,true);
        if (!empty($fields['subject']) && $this->modx->getOption('emailUseFieldForSubject',$this->formit->config,true)) {
            $subject = $fields['subject'];
        } else {
            $subject = $this->modx->getOption('emailSubject',$this->formit->config,'');
        }

        /* check email to */
        $emailTo = $this->modx->getOption('emailTo',$this->formit->config,'');
        $emailToName = $this->modx->getOption('emailToName',$this->formit->config,$emailTo);
        if (empty($emailTo)) {
            $this->errors['emailTo'] = $this->modx->lexicon('formit.email_no_recipient');
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[FormIt] '.$this->modx->lexicon('formit.email_no_recipient'));
            return false;
        }

        /* compile message */
        if (empty($tpl)) {
            $tpl = 'email';
            $f = '';
            foreach ($fields as $k => $v) {
                if ($k == 'nospam') continue;
                $f .= '<strong>'.$k.'</strong>: '.$v.'<br />'."\n";
            }
            $fields['fields'] = $f;
        }
        $message = $this->formit->getChunk($tpl,$fields);

        /* load mail service */
        $this->modx->getService('mail', 'mail.modPHPMailer');
        $this->modx->mail->set(modMail::MAIL_BODY, $message);
        $this->modx->mail->set(modMail::MAIL_FROM, $emailFrom);
        $this->modx->mail->set(modMail::MAIL_FROM_NAME, $emailFromName);
        $this->modx->mail->set(modMail::MAIL_SENDER, $emailFrom);
        $this->modx->mail->set(modMail::MAIL_SUBJECT, $subject);

        if ($this->modx->getOption('smtpEnabled',$this->formit->config,false)) {
            $this->modx->mail->set(modMail::MAIL_ENGINE, 'smtp');
            $this->modx->mail->set(modMail::MAIL_SMTP_AUTH, $this->modx->getOption('smtpAuth',$this->formit->config,'false'));
            $this->modx->mail->set(modMail::MAIL_SMTP_HOSTS,$this->modx->getOption('smtpHost',$this->formit->config,'localhost'));
            $this->modx->mail->set(modMail::MAIL_SMTP_PASS,$this->modx->getOption('smtpPassword',$this->formit->config,'password'));
            $this->modx->mail->set(modMail::MAIL_SMTP_PORT,$this->modx->getOption('smtpPort',$this->formit->config,587));
            $this->modx->mail->set(modMail::MAIL_SMTP_USER,$this->modx->getOption('smtpUsername',$this->formit->config,'username'));
            $this->modx->mail->set(modMail::MAIL_SMTP_PREFIX,$this->modx->getOption('smtpPrefix',$this->formit->config,''));
        }

        /* add to: with support for multiple addresses */
        $emailTo = explode(',',$emailTo);
        $emailToName = explode(',',$emailToName);
        $numAddresses = count($emailTo);
        for ($i=0;$i<$numAddresses;$i++) {
            $etn = !empty($emailToName[$i]) ? $emailToName[$i] : '';
            $this->modx->mail->address('to',$emailTo[$i],$etn);
        }
        $this->modx->mail->address('reply-to',$emailFrom);
        $this->modx->mail->setHTML($emailHtml);

        /* send email */
        $sent = $this->modx->mail->send();
        $this->modx->mail->reset(array(
            modMail::MAIL_CHARSET => $this->modx->getOption('mail_charset',null,'UTF-8'),
            modMail::MAIL_ENCODING => $this->modx->getOption('mail_encoding',null,'8bit'),
        ));

        if (!$sent) {
            $this->errors[] = $this->modx->lexicon('formit.email_not_sent');
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[FormIt] '.$this->modx->lexicon('formit.email_not_sent'));
        }

        return $sent;
    }

    /**
     * Ensure the a field passes a spam filter.
     *
     * Properties:
     * - spamEmailFields - The email fields to check. A comma-delimited list.
     *
     * @access public
     * @param array $fields An array of cleaned POST fields
     * @return boolean True if email was successfully sent.
     */
    public function spam(array $fields = array()) {
        $passed = true;
        $spamEmailFields = $this->modx->getOption('spamEmailFields',$this->formit->config,'email');
        $emails = explode(',',$spamEmailFields);
        if ($this->modx->loadClass('stopforumspam.StopForumSpam',$this->formit->config['modelPath'],true,true)) {
            $sfspam = new StopForumSpam($this->modx);
            foreach ($emails as $email) {
                $spamResult = $sfspam->check($_SERVER['REMOTE_ADDR'],$fields[$email]);
                if (!empty($spamResult)) {
                    $spamFields = implode($this->modx->lexicon('formit.spam_marked')."\n<br />",$spamResult);
                    $this->errors[$email] = $this->modx->lexicon('formit.spam_blocked',array(
                        'fields' => $spamFields,
                    ));
                    $passed = false;
                }
            }
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[FormIt] Couldnt load StopForumSpam class.');
        }
        return $passed;
    }

    /**
     * Adds in reCaptcha support to FormIt
     * 
     * @access public
     * @param array $fields An array of cleaned POST fields
     * @return boolean True if email was successfully sent.
     */
    public function recaptcha(array $fields = array()) {
        $passed = false;
        $recaptcha = $this->modx->getService('recaptcha','reCaptcha',$this->formit->config['modelPath'].'recaptcha/');
        if (!($recaptcha instanceof reCaptcha)) return $passed;
        if (empty($recaptcha->config[reCaptcha::OPT_PRIVATE_KEY])) return $passed;

        $response = $recaptcha->checkAnswer($_SERVER['REMOTE_ADDR'],$_POST['recaptcha_challenge_field'],$_POST['recaptcha_response_field']);

        if (!$response->is_valid) {
            $this->errors['recaptcha'] = $this->modx->lexicon('recaptcha.incorrect',array(
                'error' => $response->error != 'incorrect-captcha-sol' ? $response->error : '',
            ));
        } else {
            $passed = true;
        }
        return $passed;
    }

}