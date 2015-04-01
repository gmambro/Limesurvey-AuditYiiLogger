<?php
    class AuditYiiLogger extends PluginBase {

        static protected $description = 'Audit log of changes using Yii';
        static protected $name = 'Audit To Logger';
       
        
        public function __construct(PluginManager $manager, $id) {
            parent::__construct($manager, $id);

            $this->subscribe('beforeUserSave');
            $this->subscribe('beforeUserDelete');
            $this->subscribe('beforePermissionSetSave'); 
            $this->subscribe('beforeParticipantSave'); 
            $this->subscribe('beforeParticipantDelete'); 
            $this->subscribe('beforeLogout');
            $this->subscribe('afterSuccessfulLogin');
            $this->subscribe('afterFailedLoginAttempt');
        }

        /**
        * User logout to the audit log
        * @return unknown_type
        */
        public function beforeLogout()
        {
            $oUser = $this->api->getCurrentUser();
            if ($oUser != false)
            {
                $iUserID = $oUser->uid;
                $this->logEvent('user', $iUserID, 'beforeLogout');
            }
        }

        /**
        * Successfull login to the audit log
        * @return unknown_type
        */
        public function afterSuccessfulLogin()
        {
            $iUserID=$this->api->getCurrentUser()->uid;
            $oAutoLog->uid=$iUserID;
            $this->logEvent('user', $iUserID, 'afterSuccessfullogin');
        }

        /**
        * Failed login attempt to the audit log
        * @return unknown_type
        */
        public function afterFailedLoginAttempt()
        {
            $event = $this->getEvent();
            $identity = $event->get('identity');
            $aUsername['username'] = $identity->username;


            $this->logEvent('user', NULL, 'afterFailedLoginAttempt', NULL, NULL, $aUsername);
        }

        /**
        * Saves permissions changes to the audit log
        */
        public function beforePermissionSetSave()
        {
            $event = $this->getEvent();
            $aNewPermissions=$event->get('aNewPermissions');
            $iSurveyID=$event->get('iSurveyID');
            $iUserID=$event->get('iUserID');
            $oCurrentUser=$this->api->getCurrentUser();
            $oOldPermission=$this->api->getPermissionSet($iUserID, $iSurveyID, 'survey');
            $sAction='update';   // Permissions are in general only updated (either you have a permission or you don't)

            if (count(array_diff_assoc_recursive($aNewPermissions,$oOldPermission)))
            {
                $entity='permission';
                $entityid=$iSurveyID;
                $action=$sAction;
                $fields=array_keys(array_diff_assoc_recursive($aNewPermissions,$oOldPermission));
                $oldvalues=array_diff_assoc_recursive($oOldPermission,$aNewPermissions);
                $newvalues=array_diff_assoc_recursive($aNewPermissions,$oOldPermission);
                $this->logEvent($entity, $entityid, $action, $fields, $oldvalues, $newvalues);
            }
        }
        
        /**
        * Function catches if a participant was modified or created
        * All data is saved - only the password hash is anonymized for security reasons
        */
        public function beforeParticipantSave()
        {
            $oNewParticipant=$this->getEvent()->get('model');
            if ($oNewParticipant->isNewRecord)
            {
                return;
            }
            $oCurrentUser=$this->api->getCurrentUser();

            $aOldValues=$this->api->getParticipant($oNewParticipant->participant_id)->getAttributes();
            $aNewValues=$oNewParticipant->getAttributes();

            if (count(array_diff_assoc($aNewValues,$aOldValues)))
            {
                $entity='participant';
                $entityid=$aNewValues['participant_id'];
                $oldvalues=array_diff_assoc($aOldValues,$aNewValues);
                $newvalues=array_diff_assoc($aNewValues,$aOldValues);
                $fields=array_keys(array_diff_assoc($aNewValues,$aOldValues));
                $this->logEvent($entity, $entityid, $action, $fields, $oldvalues, $newvalues);
            }
        }        
        
        /**
        * Function catches if a participant was modified or created
        * All data is saved - only the password hash is anonymized for security reasons
        */
        public function beforeParticipantDelete()
        {
            $oNewParticipant=$this->getEvent()->get('model');
            $oCurrentUser=$this->api->getCurrentUser();

            $aValues=$oNewParticipant->getAttributes();

            $entity='participant';
            $action='delete';
            $entityid=$aValues['participant_id'];
            $fieldd=array_keys($aValues);
            $oldvalues=json_encode($aValues);
            $newvalues=NULL;
            $this->logEvent($entity, $entityid, $action, $fields, $oldvalues, $newvalues);
        }            
        
        
        /**
        * Function catches if a user was modified or created
        * All data is saved - only the password hash is anonymized for security reasons
        */
        public function beforeUserSave()
        {
            $oUserData=$this->getEvent()->get('model');
            $oCurrentUser=$this->api->getCurrentUser();
            
            $aNewValues=$oUserData->getAttributes();
            if (!isset($oUserData->uid))
            {
                $sAction='create';
                $aOldValues=array();
                // Indicate the password has changed but assign fake hash
                $aNewValues['password']='*MASKED*PASSWORD*';
            }
            else
            {                
                $oOldUser=$this->api->getUser($oUserData->uid);
                $sAction='update';
                $aOldValues=$oOldUser->getAttributes();
                
                // Postgres delivers bytea fields as streams
                if (gettype($aOldValues['password'])=='resource')
                {
                    $aOldValues['password'] = stream_get_contents($aOldValues['password']);
                }
                // If the password has changed then indicate that it has changed but assign fake hashes
                if ($aNewValues['password']!=$aOldValues['password'])
                {
                    $aOldValues['password']='*MASKED*OLD*PASSWORD*';
                    $aNewValues['password']='*MASKED*NEW*PASSWORD*';
                };
            }
            
            if (count(array_diff_assoc($aNewValues,$aOldValues)))
            {
                $entity='user';
                $entityid =  ($sAction=='update') ? $oAutoLog->entityid=$oOldUser['uid'] : "";
                $action=$sAction;
                $oldvalues=array_diff_assoc($aOldValues,$aNewValues);
                $newvalues=array_diff_assoc($aNewValues,$aOldValues);
                $fields=array_keys(array_diff_assoc($aNewValues,$aOldValues));
            	$this->logEvent($entity, $entityid, $action, $fields, $oldvalues, $newvalues);
            }
        }
                                                            
        public function beforeUserDelete()
        {
            $oUserData=$this->getEvent()->get('model');
            $oCurrentUser=$this->api->getCurrentUser();
            $oOldUser=$this->api->getUser($oUserData->uid);
            if ($oOldUser)
            {
                $aOldValues=$oOldUser->getAttributes();
                unset($aOldValues['password']);
                $oAutoLog = $this->api->newModel($this, 'log');
                $entity='user';
                $entityid=$oOldUser['uid'];
                $action='delete';
                $oldvalues=$aOldValues;
                $fields=array_keys($aOldValues);
            	$this->logEvent($entity, $entityid, $action, $fields, $oldvalues, $newvalues);
            }
        }

        protected function logEvent($entity, $entity_id, $action, 
                                    $fields=NULL, $old_values=NULL, $new_values=NULL) 
        {

            $oCurrentUser=$this->api->getCurrentUser();
            
            # events can be generated by other plugins, e.g. setPermissione fired by newSession
            $strUid = is_object($oCurrentUser) ? $oCurrentUser->uid : "system";
            
            $aMessage = array(
                'uid'        => $strUid,
                'entity'     => $entity,
                'entity_id'  => $entity_id,
                'action'     => $fields,
                'old_values' => $old_values,
                'new_values' => $new_values,
            );

            $strMessage = json_encode($aMessage);
            
            Yii::log($strMessage, 'info', 'application.Audit');
        }

    }

?>
