<?php
/* ----------------------------------------------------------------------
 * app/controllers/editor/objects/EntityEditorController.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2008 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */
 
 	require_once(__CA_MODELS_DIR__."/ca_entities.php");
 	require_once(__CA_LIB_DIR__."/ca/BaseEditorController.php");
 
 	class EntityEditorController extends BaseEditorController {
 		# -------------------------------------------------------
 		protected $ops_table_name = 'ca_entities';		// name of "subject" table (what we're editing)
 		# -------------------------------------------------------
 		public function __construct(&$po_request, &$po_response, $pa_view_paths=null) {
 			parent::__construct($po_request, $po_response, $pa_view_paths);
 		}

 			/**
 			 *
 			 * #PROMEMORIA#
 			 * Salvataggio automatico degli accessi per quello specifico Utente/Gruppo
 			 * @param BaseModel $pt_subject
 			 * @param bool $pb_was_insert
 			 */
 			protected function _afterSave($pt_subject, $pb_was_insert) {

 				if ((int)$pt_subject->getAppConfig()->get('set_access_user_groups_for_' . $pt_subject->tableName()) == 0)
 					return true;

 				$user = $this->opo_request->user;
 				// Se l'utente non è amministratore salvo l'accesso come il gruppo
 				if (!$user->canDoAction('is_administrator')) {

 					$user_id = $user->getUserID();
 					$user_groups = $user->getUserGroups();

 					// Save group ACL's
 					$va_groups_to_set = array();
 					foreach ($user_groups as $vs_key => $vs_val) {
 						$va_groups_to_set[$vs_key] = 3;
 					}
 					$pt_subject->setACLUserGroups($va_groups_to_set);

 					// Save "world" ACL
 					$pt_subject->setACLWorldAccess($pt_subject->getAppConfig()->get('ca_item_access_level'));

 					// Propagate ACL settings to records that inherit from this one
 					if ((bool)$pt_subject->getProperty('SUPPORTS_ACL_INHERITANCE')) {
 						ca_acl::applyACLInheritanceToChildrenFromRow($pt_subject);
 						if (is_array($va_inheritors = $pt_subject->getProperty('ACL_INHERITANCE_LIST'))) {
 							foreach ($va_inheritors as $vs_inheritor_table) {
 								ca_acl::applyACLInheritanceToRelatedFromRow($pt_subject, $vs_inheritor_table);
 							}
 						}
 					}

 					// Set ACL-related intrinsic fields
 		            $pt_subject->setMode(ACCESS_WRITE);
 		            $pt_subject->set('acl_inherit_from_ca_collections', $pt_subject->getAppConfig()->get('ca_objects_acl_inherit_from_ca_collections_default'));
 		            $pt_subject->set('acl_inherit_from_parent', $pt_subject->getAppConfig()->get('ca_objects_acl_inherit_from_ca_collections_default'));
 		            $pt_subject->update();

 					if ((int)$pt_subject->getAppConfig()->get('access_from_parent') == 1)	{
 						$parent_access = new ca_entities($pt_subject->get('parent_id'));
 						$parent_access = $parent_access->get('access');
 						$pt_subject->setMode(ACCESS_WRITE);
 						$pt_subject->set('access', $parent_access);
 						$pt_subject->update();
 					}

 					if ($pt_subject->numErrors()) return false;
 				}

 				return true;
 			}

 		# -------------------------------------------------------
 		# Sidebar info handler
 		# -------------------------------------------------------
 		public function info($pa_parameters) {
 			parent::info($pa_parameters);
 			return $this->render('widget_entity_info_html.php', true);
 		}
 		# -------------------------------------------------------
 	}
 ?>