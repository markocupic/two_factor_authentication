<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace MCupic;


/**
 * Front end module "login".
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ModuleTwoFactorAuthentication extends \Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_two_factor_authentication';

    /**
     * @var null
     */
    protected $error = null;

    /**
     * Display a login form
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var \BackendTemplate|object $objTemplate */
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['twoFactorAuthentication'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }
        global $objPage;

        if (!FE_USER_LOGGED_IN)
        {
            return '';
        }

        $blnRequiresTwoFactorAuthentication = false;


        $objMember = \FrontendUser::getInstance();


        $strRedirect = null;
        if ($objMember !== null)
        {
            $arrGroups = deserialize($objMember->groups);

            if (!empty($arrGroups) && is_array($arrGroups))
            {
                $objGroupPage = TwoFactorAuthentication::findFirstActiveWithJumpToTwoFactorByIds($arrGroups);

                if ($objGroupPage !== null)
                {
                    $blnRequiresTwoFactorAuthentication = true;
                }
            }
        }

        if ($blnRequiresTwoFactorAuthentication === false)
        {
            return 'Two Factor Authentication is not required!';
        }


        // Set case
        if (TwoFactorAuthentication::isLoggedIn())
        {
            $this->case = 'caseLoggedIn';

            // Search for a jumpTo-page
            $objMember = \FrontendUser::getInstance();
            if ($objMember !== null)
            {
                $arrGroups = deserialize($objMember->groups);

                if (!empty($arrGroups) && is_array($arrGroups))
                {
                    $objGroupPage = \MemberGroupModel::findFirstActiveWithJumpToByIds($arrGroups);

                    if ($objGroupPage !== null)
                    {
                        if ($objPage->alias != $objGroupPage->alias)
                        {
                            $this->jumpToOrReload($objGroupPage->row());
                        }
                    }
                }
            }
        }
        elseif ($_SESSION['CASE_ENTER_TWO_FACTOR_AUTHENTIFICATION_CODE'])
        {
            if (TwoFactorAuthentication::hasValidCode())
            {
                $this->case = 'caseEnterCode';
            }
            else
            {
                $this->case = 'caseEnterEmail';
                $_SESSION['TWO_FACTOR_ERROR'] = $GLOBALS['TL_LANG']['MSC']['noCodeFound'];
            }
        }
        else
        {
            $this->case = 'caseEnterEmail';
        }


        // ENTER E-Mail
        if (\Input::post('FORM_SUBMIT') == 'tl_two_factor_authentification_enter_email')
        {
            // Check whether username and password are set
            if (empty($_POST['email']))
            {
                $_SESSION['TWO_FACTOR_ERROR'] = $GLOBALS['TL_LANG']['MSC']['enterValidEmail'];
                $this->reload();
            }

            if (strtolower(\Input::post('email')) == $objMember->email)
            {

                $_SESSION['CASE_ENTER_TWO_FACTOR_AUTHENTIFICATION_CODE'] = true;
                $strUa = 'N/A';
                $strIp = '127.0.0.1';

                if (\Environment::get('httpUserAgent'))
                {
                    $strUa = \Environment::get('httpUserAgent');
                }
                if (\Environment::get('remoteAddr'))
                {
                    $strIp = \Environment::get('ip');
                }
                $objModel = new \TwoFactorAuthenticationModel();
                $objModel->pid = $objMember->id;
                $objModel->ip = $strIp;
                $objModel->browser = $strUa;
                $objModel->verification_email_token = rand(111111, 999999);
                $objModel->tstamp = time();
                $objModel->expiresOn = time() + $GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'];
                $objModel->save();

                // Send Email
                $email = new \Email();
                $email->subject = 'Sicherheitscode für das "' . \Environment::get('host') . '" Konto';
                // Set the admin e-mail as "from" address
                $email->from = $GLOBALS['TL_ADMIN_EMAIL'];
                $email->fromName = 'Administrator';
                $replyTo = '"' . 'Administrator' . '" <' . $GLOBALS['TL_ADMIN_EMAIL'] . '>';
                $email->replyTo($replyTo);
                $objTextTemplate = new \FrontendTemplate('two_factor_authentication_email_body');
                $objTextTemplate->host = \Environment::get('host');
                $objTextTemplate->code = $objModel->verification_email_token;
                $body = $objTextTemplate->parse();
                $email->text = \StringUtil::decodeEntities(trim($body));
                $email->sendTo($objMember->email);

                $this->reload();

            }
            else
            {
                $_SESSION['TWO_FACTOR_ERROR'] = $GLOBALS['TL_LANG']['MSC']['enterValidEmail'];
                $this->reload();
            }
        }

        // ENTER Code
        if (\Input::post('FORM_SUBMIT') == 'tl_two_factor_authentification_enter_code')
        {
            // Check whether username and password are set
            if (!empty($_POST['verification_email_token']))
            {
                $objSet = \Database::getInstance()->prepare('SELECT * FROM tl_two_factor_authentication WHERE pid=? AND verification_email_token = ? AND activated = ? AND tstamp > ?')->limit(1)->execute($objMember->id, \Input::post('verification_email_token'), '', time() - 600);
                if ($objSet->numRows)
                {
                    $objTFAM = \TwoFactorAuthenticationModel::findByPk($objSet->id);
                    if ($objTFAM !== null)
                    {
                        $objTFAM->activated = '1';
                        $objTFAM->save();
                        unset($_SESSION['CASE_ENTER_TWO_FACTOR_AUTHENTIFICATION_CODE']);
                        $this->reload();
                    }
                }
                $_SESSION['TWO_FACTOR_ERROR'] = $GLOBALS['TL_LANG']['MSC']['invalidCode'];
                $this->reload();
            }

        }

        // unset $_SESSION['TWO_FACTOR_LOGIN_ERROR'] and store it in $this->errorMsg
        if (isset($_SESSION['TWO_FACTOR_ERROR']))
        {
            $this->errorMsg = $_SESSION['TWO_FACTOR_ERROR'];
            unset($_SESSION['TWO_FACTOR_ERROR']);
        }


        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {
        $blnHasError = false;
        if ($this->case == 'caseLoggedIn')
        {
            $this->Template->caseLoggedIn = true;
        }
        elseif ($this->case == 'caseEnterEmail')
        {
            $objMember = \FrontendUser::getInstance();
            if ($this->errorMsg)
            {
                $this->Template->error = $this->errorMsg;
            }
            $this->Template->emailHint = TwoFactorAuthentication::anonymizeEmail($objMember->email);
            $this->Template->caseEnterEmail = true;
            $this->Template->formSubmit = 'tl_two_factor_authentification_enter_email';
            $this->Template->labelYourEmailAdress = $GLOBALS['TL_LANG']['MSC']['labelYourEmailAdress'];

        }
        if ($this->case == 'caseEnterCode')
        {
            $objMember = \FrontendUser::getInstance();
            if ($this->errorMsg)
            {
                $this->Template->error = $this->errorMsg;
            }
            $this->Template->caseEnterCode = true;
            $this->Template->email = $objMember->email;
            $this->Template->labelTwoFactorAuthenticationCode = $GLOBALS['TL_LANG']['MSC']['labelTwoFactorAuthenticationCode'];
            $this->Template->formSubmit = 'tl_two_factor_authentification_enter_code';
        }


        $this->Template->action = ampersand(\Environment::get('indexFreeRequest'));
        $this->Template->slabel = specialchars($GLOBALS['TL_LANG']['MSC']['login']);

    }


}
