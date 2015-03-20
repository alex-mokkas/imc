<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
jimport('joomla.plugin.plugin');
require_once JPATH_COMPONENT_SITE . '/helpers/imc.php';

class plgImcmail_notifier extends JPlugin
{

	public function onAfterNewIssueAdded($model, $validData, $id = null)
	{

		$details = $this->getDetails($id, $model);
		$app = JFactory::getApplication();

		$showMsgsFrontend = ($this->params->get('messagesfrontend') && !$app->isAdmin());
		$showMsgsBackend  = ($this->params->get('messagesbackend') && $app->isAdmin());

		//Prepare email for admins
		if ($this->params->get('mailnewissueadmins')){
			$subject = sprintf(
				JText::_('PLG_IMC_MAIL_NOTIFIER_ADMINS_NEW_ISSUE_SUBJECT'), 
				$details->username, 
				$details->usermail
			);

			$body = sprintf(
				JText::_('PLG_IMC_MAIL_NOTIFIER_ADMINS_NEW_ISSUE_BODY'),
				ImcFrontendHelper::getCategoryNameByCategoryId($validData['catid']),
				$validData['title'],
				$validData['address']
				//$validData['description'],
				//$issueLink
				//$issueAdminLink 
			);
		
			if(empty($details->emails) || $details->emails[0] == ''){
				if($showMsgsBackend)
					$app->enqueueMessage(JText::_('PLG_IMC_MAIL_NOTIFIER_ADMINS_MAIL_NOT_SET').ImcFrontendHelper::getCategoryNameByCategoryId($validData['catid']), 'warning');
			}
			else {
				$recipients = implode(',', $details->emails);
				if ($this->sendMail($subject, $body, $details->emails) ) {
					if($showMsgsBackend)
						$app->enqueueMessage(JText::_('PLG_IMC_MAIL_NOTIFIER_ADMINS_MAIL_CONFIRM').$recipients);
				}
				else {
					if($showMsgsBackend)
						$app->enqueueMessage(JText::_('PLG_IMC_MAIL_NOTIFIER_MAIL_FAILED').$recipients, 'error');
				}
			}
		}

		//Prepare email for user
		if ($this->params->get('mailnewissueuser')) {		
			
			$subject = sprintf(
				JText::_('PLG_IMC_MAIL_NOTIFIER_USER_NEW_ISSUE_SUBJECT'), 
				$validData['title']
			);

			$body = sprintf(
				JText::_('PLG_IMC_MAIL_NOTIFIER_USER_NEW_ISSUE_BODY'),
				ImcFrontendHelper::getCategoryNameByCategoryId($validData['catid'])
				//$validData['title'],
				//$validData['address']
				//$validData['description'],
				//$issueLink
				//$issueAdminLink 
			);

			if ($this->sendMail($subject, $body, $details->usermail) ) {
				if($showMsgsBackend){
					//do we really want to sent confirmation mail if issue is submitted from backend?
					$app->enqueueMessage(JText::_('PLG_IMC_MAIL_NOTIFIER_MAIL_CONFIRM').$details->usermail);
				}
				if($showMsgsFrontend){
					$app->enqueueMessage(JText::_('PLG_IMC_MAIL_NOTIFIER_MAIL_CONFIRM').$details->usermail);
				}				
			}
			else {
				$app->enqueueMessage(JText::_('PLG_IMC_MAIL_NOTIFIER_MAIL_FAILED').$recipients, 'error');
			}

		}
	}	

	public function onAfterStepModified($model, $validData, $id = null)
	{
		$details = $this->getDetails($id, $model);
		
		//Prepare email for admins
		//TODO: Do we really need to notify admins to every issue status modification? Set this on settings
		if(empty($details->emails) || $details->emails[0] == ''){
			JFactory::getApplication()->enqueueMessage('Admin notifications when issue status modified are not set', 'Info');
		}
		else {
			$recipients = implode(',', $details->emails);
			JFactory::getApplication()->enqueueMessage('Admin notification mail due to issue status modification is sent to '.$recipients, 'Info');
		}

		//Prepare email for user
		JFactory::getApplication()->enqueueMessage('User notification mail (because issue status has modified) sent to '.$details->username.' at '.$details->usermail, 'Info');
	}	

	public function onAfterCategoryModified($model, $validData, $id = null)
	{
		$details = $this->getDetails($id, $model);
		
		//Prepare email for admins
		if(empty($details->emails) || $details->emails[0] == ''){
			JFactory::getApplication()->enqueueMessage('Admin notifications when category has modified are not set', 'Info');
		}
		else {
			$recipients = implode(',', $details->emails);
			JFactory::getApplication()->enqueueMessage('Notification mail due to category modification is sent to '.$recipients, 'Info');
		}

		//Prepare email for user
		//TODO: Do we really need to notify user for categeory modification? Set this on settings
		JFactory::getApplication()->enqueueMessage('Notification mail (because category has modified) sent to '.$details->username.' at '.$details->usermail, 'Info');
	}	

	private function sendMail($subject, $body, $recipients) {
		$app = JFactory::getApplication();
		$mailfrom	= $app->getCfg('mailfrom');
		$fromname	= $app->getCfg('fromname');
		$sitename	= $app->getCfg('sitename');

		$mail = JFactory::getMailer();
		$mail->isHTML(true);
		$mail->Encoding = 'base64';
		if(is_array($recipients)){
			foreach($recipients as $recipient){
				$mail->addRecipient($recipient);
			}
		}
		else {
			$mail->addRecipient($recipients);
		}
		$mail->setSender(array($mailfrom, $fromname));
		$mail->setSubject($sitename.': '.$subject);
		$mail->setBody($body);
		if ($mail->Send()) {
		  return true;
		} else {
		  return false;
		}			
	}

	private function getDetails($id, $model) {
		//check if issue added from frontend
		if($id == null){
			$issueid = $model->getItem()->get('id');
		} 
		else {
			$issueid = $id;
		}

		//$emails = $model->getItem($issueid)->get('notification_emails');
		JModelLegacy::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
		$issueModel = JModelLegacy::getInstance( 'Issue', 'ImcModel' );
		$emails = $issueModel->getItem($issueid)->get('notification_emails');

		$userid = $issueModel->getItem($issueid)->get('created_by');
		$username = JFactory::getUser($userid)->name;
		$usermail = JFactory::getUser($userid)->email;

		$details = new stdClass();
		$details->issueid = $issueid;
		$details->emails = $emails;
		$details->userid = $userid;
		$details->username = $username;
		$details->usermail = $usermail;

		return $details;
	}
}
