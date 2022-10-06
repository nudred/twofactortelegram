<?php

use dokuwiki\Form\Form;
use dokuwiki\plugin\twofactor\OtpField;
use dokuwiki\plugin\twofactor\Provider;

/**
 * 2fa provider using an Telegram
 */
class action_plugin_twofactortelegram extends Provider
{
    /** @inheritdoc */
    public function getLabel()
    {
        $label = $this->getLang('name');
        $chat_id = $this->settings->get('chat_id');
        if ($chat_id) $label .= ': ' . $chat_id;
        return $label;
    }

    /** @inheritdoc */
    public function isConfigured()
    {
        return $this->settings->get('chat_id') &&
            $this->settings->get('verified');

    }

    /** @inheritdoc */
    public function renderProfileForm(Form $form)
    {
        $chat_id = $this->settings->get('chat_id');

        if (!$chat_id) {
            $form->addHTML('<p>' . $this->getLang('intro') . '</p>');
            $form->addTextInput('new_chat_id', $this->getLang('chat_id'))->attr('autocomplete', 'off');
        } else {
            $form->addHTML('<p>' . $this->getLang('verifynotice') . '</p>');
            $form->addElement(new OtpField('verify'));
        }

        return $form;
    }

    /** @inheritdoc */
    public function handleProfileForm()
    {
        global $INPUT;
        global $USERINFO;

        if ($INPUT->str('verify')) {
            // verification code given, check the code
            if ($this->checkCode($INPUT->str('verify'))) {
                $this->settings->set('verified', true);
            } else {
                $this->settings->delete('chat_id');
            }
        } elseif ($INPUT->str('new_chat_id')) {
            $new_chat_id = $INPUT->str('new_chat_id');

            // new chat_id has been, set init verification
            $this->settings->set('chat_id', $new_chat_id);

            try {
                $this->initSecret();
                $code = $this->generateCode();
                $info = $this->transmitMessage($code);
                msg(hsc($info), 1);
            } catch (\Exception $e) {
                msg(hsc($e->getMessage()), -1);
                $this->settings->delete('chat_id');
            }
        }
    }

    /** @inheritdoc */
    public function transmitMessage($code)
    {
        $chat_id = $this->settings->get('chat_id');
        $token = $this->getConf('bot_token');

        if (!$chat_id) throw new \Exception($this->getLang('codesentfail'));
        if (!$token) throw new \Exception($this->getLang('codesentfail'));

		$text = rawurlencode($this->getLang('text'));
		$code = rawurlencode("`$code`");
		$url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat_id}&text={$text}{$code}&parse_mode=MarkdownV2";
		$result = file_get_contents($url);

        if (!$result) throw new \Exception($this->getLang('codesentfail'));

        return $this->getLang('codesent');
    }
}
