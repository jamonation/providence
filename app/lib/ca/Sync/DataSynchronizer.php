<?php
/** ---------------------------------------------------------------------
 * app/lib/ca/Sync/DataSynchronizer.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2013-2015 Whirl-i-Gig
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
 * @package CollectiveAccess
 * @subpackage BaseModel
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */
 define("__CA_DONT_DO_SEARCH_INDEXING__", true);
 /**
  *
  */  
require_once(__CA_LIB_DIR__.'/core/Configuration.php');
require_once(__CA_LIB_DIR__.'/core/Datamodel.php');
require_once(__CA_LIB_DIR__.'/ca/Service/RestClient.php');
require_once(__CA_MODELS_DIR__.'/ca_lists.php');
require_once(__CA_MODELS_DIR__.'/ca_relationship_types.php');
require_once(__CA_MODELS_DIR__.'/ca_data_import_events.php');
require_once(__CA_LIB_DIR__.'/core/Logging/KLogger/KLogger.php');
  
  class DataSynchronizer  {
	# ------------------------------------------------------------------
	/**
	 *
	 */
	private $opa_processed_id_to_idno = array();
	/**
	 *
	 */
	private $opa_processed_records = array();
	
	/**
	 *
	 */
	private $opa_processed_self_relations = array();
	
	/**
     * Valid PHP date() format string for log timestamps
     * @var string
     */
    private static $_dateFormat         = 'Y-m-d G:i:s';
    
    /**
     *
     */
    private $locale = null;
    
     /**
     *
     */
    private $datamodel = null;
    
     /**
     *
     */
    private $lists = null;
    
	# ------------------------------------------------------------------
	public function __construct() {
		$this->locale = new ca_locales();
		$this->lists = new ca_lists();
		$this->datamodel = Datamodel::load();
	}
	# ------------------------------------------------------------------
	/**
	 *logLevel = KLogger constant for minimum log level to record. Default is KLogger::INFO. Constants are, in descending order of shrillness:
	 *			KLogger::EMERG = Emergency messages (system is unusable)
	 *			KLogger::ALERT = Alert messages (action must be taken immediately)
	 *			KLogger::CRIT = Critical conditions
	 *			KLogger::ERR = Error conditions
	 *			KLogger::WARN = Warnings
	 *			KLogger::NOTICE = Notices (normal but significant conditions)
	 *			KLogger::INFO = Informational messages
	 *			KLogger::DEBUG = Debugging messages
	 *
	 */
	public function logNotice($ps_msg, $pa_options=null) {
		if (!is_null($vn_log_level = caGetOption('logLevel', $pa_options, null)) && ($vn_log_level < KLogger::NOTICE)) { return; } 
		$vs_msg_with_date = "[".date(DataSynchronizer::$_dateFormat)."] (SYNC) {$ps_msg}";
		if (caGetOption('consoleOutput', $pa_options, false)) { CLIUtils::addMessage($vs_msg_with_date); }
		if ($o_log = caGetOption('log', $pa_options, null)) {
			$o_log->logNotice("[Sync] {$ps_msg}");
		}
	}
	# ------------------------------------------------------------------
	/**
	 *
	 *
	 */
	public function logWarn($ps_msg, $pa_options=null) {
		if (!is_null($vn_log_level = caGetOption('logLevel', $pa_options, null)) && ($vn_log_level < KLogger::WARN)) { return; } 
		$vs_msg_with_date = "[".date(DataSynchronizer::$_dateFormat)."] (SYNC) {$ps_msg}";
		if (caGetOption('consoleOutput', $pa_options, false)) { CLIUtils::addMessage($vs_msg_with_date); }
		if ($o_log = caGetOption('log', $pa_options, null)) {
			$o_log->logWarn("[Sync] {$ps_msg}");
		}
	}
	# ------------------------------------------------------------------
	/**
	 *
	 *
	 */
	public function logError($ps_msg, $pa_options=null) {
		if (!is_null($vn_log_level = caGetOption('logLevel', $pa_options, null)) && ($vn_log_level < KLogger::ERR)) { return; } 
		$vs_msg_with_date = "[".date(DataSynchronizer::$_dateFormat)."] {$ps_msg}";
		if (caGetOption('consoleOutput', $pa_options, false)) { CLIUtils::addError($vs_msg_with_date); }
		if ($o_log = caGetOption('log', $pa_options, null)) {
			$o_log->logError("[Sync] {$ps_msg}");
		}
		
		return true;
	}
	# ------------------------------------------------------------------
	/**
	 * @param array $pa_options Options include:
	 *		consoleOutput = 
	 *		logLevel =
	 *		logDirectory = 
	 *
	 * @return bool 
	 */
	public function sync($pa_options=null) {
		$o_config 			= Configuration::load();
		$o_sync_config 		= Configuration::load(__CA_CONF_DIR__."/synchronization.conf");
		
		$pb_console_output = caGetOption('consoleOutput', $pa_options, false);
		
		if (!is_array($pa_options) || !isset($pa_options['logLevel']) || !$pa_options['logLevel']) {
			$pa_options['logLevel'] = KLogger::NOTICE;
		}
		
		if (!is_array($pa_options) || !isset($pa_options['logDirectory']) || !$pa_options['logDirectory'] || !file_exists($pa_options['logDirectory'])) {
			if (!($pa_options['logDirectory'] = $o_config->get('batch_metadata_import_log_directory'))) {
				$pa_options['logDirectory'] = ".";
			}
		}
		
		if (!is_writeable($pa_options['logDirectory'])) { $pa_options['logDirectory'] = caGetTempDirPath(); }
		$o_log = new KLogger($pa_options['logDirectory'], $pa_options['logLevel']);
		
		$pa_options['log'] = $o_log;
	
		$this->opa_processed_records = array();
		$this->opa_processed_id_to_idno = array();
		$this->opa_processed_self_relations = array();
   
	
		$va_sources = $o_sync_config->getAssoc('sources');
		foreach($va_sources as $vs_source_code => $va_source) {
			$vs_base_url 						= $va_source['baseUrl'];
			$vs_search_expression 				= $va_source['searchExpression'];
			$vs_username 						= $va_source['username'];
			$vs_password 						= $va_source['password'];
			$vs_table 							= $va_source['table'];
			
			if(!$vs_base_url){
				$this->logError(_t('Source is not a valid CollectiveAccess url'), $pa_options);
				throw new DataSynchronizerException($vs_error_msg);
				return false;
			}
			
			$this->logNotice(_t("Processing %1/%2", $vs_base_url, $vs_code), $pa_options);
		
			if (!($t_instance = $this->datamodel->getInstanceByTableName($vs_table, false))) {
				$this->logError(_t('Source table %1 is invalid', $vs_table), $pa_options);
				throw new DataSynchronizerException($vs_error_msg);
				return false;
			}
			if (!($vs_idno_fld = $t_instance->getProperty('ID_NUMBERING_ID_FIELD'))) {
				$this->logError(_t('Source table %1 must use identifiers to be sync-able', $vs_table), $pa_options);
				throw new DataSynchronizerException($vs_error_msg);
				return false;
			}
			
		
			//
			// Set up HTTP client for REST calls
			//
			$o_client = new RestClient("{$vs_base_url}/service.php/search/Search/rest");
		
			//
			// Authenticate
			//
			$o_res = $o_client->auth($vs_username, $vs_password)->get();
				if (!$o_res->isSuccess()) {
					$this->logError(_t('Service authentication failed for source %1', $vs_source_code), $pa_options);
					throw new DataSynchronizerException($vs_error_msg);
					return false;
				}
		
			//
			// Get userID
			//
			$o_res = $o_client->getUserID()->get();
			if (!$o_res->isSuccess()) {
				$this->logError(_t('Service authentication failed for source %1', $vs_source_code), $pa_options);
				throw new DataSynchronizerException($vs_error_msg);
				return false;
			}
			$vn_user_id = (int)$o_res->getUserID->response;
		
			//
			// Get date/time on server
			//
			$o_res = $o_client->getServerTime()->get();
			if (!$o_res->isSuccess()) {
				$this->logError(_t('Could not fetch server time for source %1', $vs_source_code), $pa_options);
				throw new DataSynchronizerException($vs_error_msg);
				return false;
			}
			$vn_server_timestamp = (int)$o_res->getServerTime->response;
		
			// Get last event
			$va_last_event = ca_data_import_events::getLastEventForSourceAndType($vs_base_url, 'SYNC');
			$vs_last_event_timestamp = null;
			if (is_array($va_last_event)) {
				if (preg_match('!\[([^\]]+)!', $va_last_event['description'], $va_matches)) {
					$vs_last_event_timestamp = $va_matches[1];
					$vs_search_expression = ($vs_search_expression == '*') ? "modified:\"after {$vs_last_event_timestamp}\"" : "({$vs_search_expression}) AND (modified:\"after {$vs_last_event_timestamp}\")";
				}
			} else {
				$vs_search_expression = "{$vs_search_expression}";
			}
			
			$vs_search_expression = "{$vs_search_expression}";
		
			$this->logNotice(_t("Search is for [%1] on source %2", $vs_search_expression, $vs_source_code), $pa_options);
			
			try {
				$o_res = $o_client->queryRest($vs_table, $vs_search_expression,  array("ca_objects.status" => array("convertCodesToDisplayText" => 1)))->get();
			} catch (exception $e) {
				$this->logError(_t('Search failed for source %1: %2', $vs_source_code, $e->getMessage()), $pa_options);
				continue;
			}
		
		
			//parse results
				$vs_pk = $t_instance->primaryKey();
				$va_items = array();
				$o_xml = $o_res->CaSearchResult;
				foreach($o_xml->children() as $vn_i => $o_item) {
					$o_attributes = $o_item->attributes();
					$vn_id = (int)$o_attributes->{$vs_pk};
				
					$vs_idno = (string)$o_item->idno;
					$vs_label = (string)$o_item->ca_labels->ca_label[0];
				
					$va_items[$vs_table.'/'.$vn_id] = array(
						'table' => $vs_table,
						'id' => $vn_id
					);
				}
				
				$this->logNotice(_t("Found %1 items from source %2", sizeof($va_items), $vs_source_code), $pa_options);
		
				// Ok... now fetch and import each
				$o_client->setUri("{$vs_base_url}/service.php/iteminfo/ItemInfo/rest");
				$this->fetchAndImport($va_items, $o_client, $va_source, array(), $vs_source_code, $pa_options);
			
				// Handle deletions
				$this->logNotice(_t("Searching for items to delete"), $pa_options);
				
				// NOTE: using newer services...
				$vs_service_url = str_replace("http://", "http://".$vs_username.":".$vs_password."@", $vs_base_url)."/service.php/find/{$vs_table}?q=".urlencode($vs_search_expression)."&deleted=1";
				$va_deleted_items= json_decode(file_get_contents($vs_service_url), true);
	
				if (is_array($va_deleted_items['results']) && (sizeof($va_deleted_items['results']) > 0)) {
					$this->logNotice("Found ".sizeof($va_deleted_items['results'])." items to delete", $pa_options);
					foreach($va_deleted_items['results'] as $vn_i => $va_deleted_item) {
						if ($t_instance->load(array('idno' => $va_deleted_item['idno'], 'source_id' => $vn_source_id), false)) {
							$t_instance->setMode(ACCESS_WRITE);
							$t_instance->delete(true, array('hard' => true));
							if ($t_instance->numErrors()) {
								$this->logError("Could not delete {$vs_table} ".$va_deleted_item['idno'].": ".join("; ", $t_instance->getErrors()), $pa_options);
							} else {
								$this->logNotice("Deleted {$vs_table}:idno=".$va_deleted_item['idno']."", $pa_options);
							}
							unset($g_metadata_quality_report[$va_deleted_item['idno']]);
						}
					}
				} else {
					$this->logNotice(_t("Nothing to delete"), $pa_options);
				}
			
			
				// Create new import event
				ca_data_import_events::newEvent($vn_user_id, 'SYNC', $vs_base_url, 'Sync process synchronization at ['.date("c", $vn_server_timestamp).']');	
				
				return true;
		}
	}
	# ------------------------------------------------------------------------------------------
	// TODO: Add from/until support	
	private function fetchAndImport($pa_item_queue, $po_client, $pa_config, $pa_tables, $ps_code, $pa_options=null) {
		global $g_ui_locale_id;
		
		$vs_locale = caGetOption('defaultLocale', $pa_config, null);
		
		if (!is_array($pa_tables)) { $pa_tables = array(); }
		
		$vs_base_url = $pa_config['baseUrl'];
		$vn_source_id = $this->lists->getItemIDFromList('object_sources', $pa_config['code']);
		$pn_rep_type_id = $this->lists->getItemIDFromList('object_representation_types', 'front');
		
		foreach($pa_item_queue as $vn_i => $va_item) {
			$vs_table = $va_item['table'];
			$va_import_relationships_from = $pa_config['importRelatedFor'][$va_item['table']];
			$vn_id = $va_item['id'];
			if (!$vn_id) { 
				$this->logWarn(_t("Skipped item because no id is set %1", $ps_code), $pa_options);
				continue; 
			}
				
			if(isset($this->opa_processed_records[$vs_table.'/'.$vn_id])) { continue; }		// skip if already processed
			
			try {
				$o_xml = $po_client->getItem($vs_table, $vn_id)->get();
			} catch (exception $e) {
				$this->logError(_t("While trying to get item information: %1", $e->getMessage()), $pa_options);
				continue;
			}
			$o_item = $o_xml->getItem;
			
			$t_instance = $this->datamodel->getInstanceByTableName($vs_table, false);
			$t_instance_label = $t_instance->getLabelTableInstance();
			
			if (!($vs_idno_fld = $t_instance->getProperty('ID_NUMBERING_ID_FIELD'))) {
				$this->logError(_t('Table %1 must use identifiers to be sync-able', $vs_table), $pa_options);
				throw new DataSynchronizerException($vs_error_msg);
				return false;
			}
			
			//$vs_idno = trim((string)$va_item['idno']);
			$vs_idno = $o_xml->getItem->{$vs_idno_fld};
			
			
			// Look for existing record
			$vb_skip = false;
			$vb_update = false;
			$vs_label_fld = $t_instance->getLabelDisplayField();
			
			$vs_label_locale = $vs_locale;
			if (!($vs_label = (string)$o_item->preferred_labels->{$vs_locale}->{$vs_label_fld})) {
				foreach($o_item->preferred_labels->children() as $o_label) {
					$vs_label_locale = (string)$o_label->getName();
					$vs_label = (string)$o_label->{$vs_label_fld};
				}
			}
			$vn_label_locale_id = $this->_createLocale($vs_label_locale);
			
			$this->logNotice(_t("[%1] Processing [%2] %3 (%4)", $vs_idno, $vs_table, $vs_label, $ps_code), $pa_options);
			
			$t_instance_label->clear();
			
			$vs_idno_fld = $t_instance->getProperty('ID_NUMBERING_ID_FIELD');
			if (
				($vs_idno &&  $t_instance->load(array($vs_idno_fld => $vs_idno)))
			) {
				if (!$t_instance->getPrimaryKey()) {
					$this->logError(_t("[%1] Could not load instance", $vs_idno), $pa_options);
					continue;
				}
				
				$this->logNotice(_t("[%1] Already exists in %2 so update (%3)", $vs_idno, $vs_table, $ps_code), $pa_options);
				
				$vb_update = true;
				$t_instance->setMode(ACCESS_WRITE);
				
				// Undelete record if necessary
				if ($t_instance->hasField('deleted') && ($t_instance->get('deleted') == 1)) { 
					$t_instance->set('deleted', 0);
				}
				
				// Clear labels
				$t_instance->removeAllLabels();
				if ($t_instance->numErrors()) {
					$this->logError(_t("[%1] Could not remove labels for updating: %2", $vs_idno, join("; ", $t_instance->getErrors())), $pa_options);
				}
				
				// Clear attributes
				$t_instance->removeAttributes(null, array('dontCheckMinMax' => true));
				if ($t_instance->numErrors()) {
					$this->logError(_t("[%1] Could not remove attributes for updating: %2"), $vs_idno, join("; ", $t_instance->getErrors()), $pa_options);
				}
				
				// Clear relationships
				if (is_array($va_import_relationships_from)) {
					foreach($va_import_relationships_from as $vs_rel_table => $va_table_info) {
						$t_instance->removeRelationships($vs_rel_table);
						if ($t_instance->numErrors()) {
							$this->logError("[{$vs_idno}] Could not remove {$vs_rel_table} relationships for updating: ".join("; ", $t_instance->getErrors()), $pa_options);
						}
					}
				}
				
				$t_instance->update();
				if ($t_instance->numErrors()) {
					$this->logError("[{$vs_idno}] Could not clear record for updating: ".join("; ", $t_instance->getErrors()), $pa_options);
				}
				
			}
			
			// create new one
			if (!$vb_update) { $t_instance->clear(); }
			$t_instance->setMode(ACCESS_WRITE);
			
			
			// does it have a parent?
			if ($t_instance->isHierarchical() && ($vn_parent_id = (int)$o_item->{'parent_id'})) {
				$this->logNotice(_t("[%1] Attempting to load parent id %2", $vs_idno, $vn_parent_id), $pa_options);
		
				$this->fetchAndImport(array(
					$vs_table.'/'.$vn_parent_id => array(
						'table' => $vs_table,
						'id' => $vn_parent_id
					)
				), $po_client, $pa_config, $pa_tables, $ps_code, $pa_options);
				$t_instance->get('parent_id', $vs_parent_idno = $this->opa_processed_id_to_idno[$vs_table.'/'.$vn_parent_id]);
				$this->logNotice(_t("[%1] Got idno %2 for parent id %3:%4", $vs_idno, $vs_parent_idno, $vs_table, $vn_parent_id), $pa_options);
			}
			
			
			if (!($va_restrict_to_bundles = caGetOption('restrictToBundles', $pa_options, null))) {
				if ($vs_table == $pa_config['table']) { $va_restrict_to_bundles = caGetOption('restrictToBundles', $pa_config, null); }
			}
		
			// add intrinsics
			switch($vs_table) {
				case 'ca_collections':
					$va_intrinsics = array('status', 'access', 'idno', 'source_id');
					break;
				case 'ca_occurrences':
					$va_intrinsics = array('status', 'access', 'idno', 'source_id');
					break;
				case 'ca_objects':
					// TODO: support migration of current_loc_*
					$va_intrinsics = array('status', 'access', 'idno', 'source_id', 'acquisition_type_id','item_status_id','extent', 'extent_units', 'is_deaccessioned', 'deaccession_notes', 'deaccession_type_id','access_inherit_from_parent');
					break;
				case 'ca_entities':
					$va_intrinsics = array('status', 'access', 'lifespan', 'idno', 'source_id');
					break;
				case 'ca_object_lots':
					$va_intrinsics = array('status', 'access', 'idno_stub');
					break;
				case 'ca_lists':
					$va_intrinsics = array('list_code', 'is_system_list', 'is_hierarchical', 'use_as_vocabulary', 'default_sort');
					break;
				case 'ca_list_items':
					$va_intrinsics = array('status', 'access', 'idno', 'item_value', 'rank', 'is_enabled', 'is_default', 'validation_format', 'color', 'settings', 'source_id');
					break;
				default:
					$va_intrinsics = array('status', 'access', 'idno', 'source_id');
					break;
			}
			
			if (is_array($va_restrict_to_bundles) && sizeof($va_restrict_to_bundles)) {
				foreach($va_intrinsics as $vn_i => $vs_intrinsic) {
					if (($vs_intrinsic !== 'idno') && ($vs_intrinsic !== 'idno_stub') && (!in_array($vs_intrinsic, $va_restrict_to_bundles))) {
						unset($va_intrinsics[$vn_i]);
					}
				}
			}
			
			// TODONOW: Need to properly handle foreign-key intrinsics when the item they point to doesn't exist
			// eg. source_id fields, various ca_objects and ca_object_lots intrinsics, etc.
			if ($vs_table == 'ca_list_items') { 
				// does list exist?
				$vs_list_code = (string)$o_item->{'list_code'};
				$t_list = new ca_lists();
				
				if (!$t_list->load(array('list_code' => $vs_list_code))) {
					
					$this->fetchAndImport(array(
						'ca_lists/'.(int)$o_item->{'list_id'} => array(
							'table' => 'ca_lists', 
							'id' => (int)$o_item->{'list_id'}
						)
					), $po_client, $pa_config, $pa_tables, $ps_code, $pa_options);
					$this->logNotice(_t("[%1] Created list %2 with id %3:", $vs_idno, $vs_list_code, $this->opa_processed_records['ca_lists/'.(int)$o_item->{'list_id'}]), $pa_options);
				}
				$t_instance->set('list_id', $this->opa_processed_records['ca_lists/'.(int)$o_item->{'list_id'}]);
			}
			
			foreach($va_intrinsics as $vs_f) {
				if ($vs_list_code = $t_instance->getFieldInfo($vs_f, 'LIST_CODE')) {
					// check that item exists
					$this->_createListItem($vs_list_code, $o_item->{$vs_f});
				}
				$t_instance->set($vs_f, (string)$o_item->{$vs_f});
			}
			
			
			if (!$vb_update) {
				if (!($vn_type_id = $t_instance->getTypeIDForCode((string)$o_item->type_id))) {
					$vn_type_id = $this->_createListItem($t_instance->getTypeListCode(), $o_item->{$vs_f});
				}
				
				if (!$vn_type_id) { 
					$this->logWarn("[{$vs_idno}] No type found for {$vs_table}:".(string)$o_item->type_id, $pa_options);
					continue;
				}
				
				$t_instance->set('type_id', $vn_type_id);
				
				// TODO: add hook onBeforeInsert()
				$t_instance->insert();
				// TODO: add hook onInsert()
				
				if ($t_instance->numErrors()) {
					$this->logError("[{$vs_idno}] Could not insert record: ".join('; ', $t_instance->getErrors()), $pa_options);
				}
			}
			
			// add attributes
			$va_codes = $t_instance->getApplicableElementCodes();
			if (is_array($va_restrict_to_bundles) && sizeof($va_restrict_to_bundles)) {
				foreach($va_codes as $vn_i => $vs_code) {
					if (!in_array($vs_code, $va_restrict_to_bundles)) {
						unset($va_codes[$vn_i]);
					}
				}
			}
			
			foreach($va_codes as $vs_code) {
				$t_element = $t_instance->_getElementInstance($vs_code);
				
				switch($t_element->get('datatype')) {
					case __CA_ATTRIBUTE_VALUE_CONTAINER__:
						$va_elements = $t_element->getElementsInSet();
						
						$o_attr = $o_item->{'ca_attribute_'.$vs_code};
						foreach($o_attr as $va_tag => $o_tags) {
							foreach($o_tags as $vs_locale => $o_values) {
								if (!($vn_locale_id = $this->_createLocale($vs_locale))) { 
									throw new DataSynchronizerException(_t('Could not create locale %1', $vs_locale));
								}
								$va_container_data = array('locale_id' => $vn_locale_id);
								foreach($o_values as $o_value) {
								
									foreach($va_elements as $vn_i => $va_element_info) {
										if ($va_element_info['datatype'] == __CA_ATTRIBUTE_VALUE_CONTAINER__) { continue; }	
								
										if ($vs_value = trim((string)$o_value->{$va_element_info['element_code']})) {
											switch($va_element_info['datatype']) {
												case __CA_ATTRIBUTE_VALUE_LIST__:
													$va_tmp = explode(":", $vs_value);		//<item_id>:<item_idno>
													$va_container_data[$va_element_info['element_code']] = $this->lists->getItemIDFromList($va_element_info['list_id'], $va_tmp[1]);
													break;
												default:
													$va_container_data[$va_element_info['element_code']] = $vs_value;
													break;
											}
										}
									}
									
									$t_instance->replaceAttribute(
											$va_container_data,
											$vs_code
									);
								}
							}
						}
						break;
					case __CA_ATTRIBUTE_VALUE_LIST__:
						$o_attr = $o_item->{'ca_attribute_'.$vs_code};
						foreach($o_attr as $va_tag => $o_tags) {
							foreach($o_tags as $vs_locale => $o_values) {
								if (!($vn_locale_id = $this->_createLocale($vs_locale))) { 
									throw new DataSynchronizerException(_t('Could not create locale %1', $vs_locale));
								}
								foreach($o_values as $o_value) {
									if ($vs_value = trim((string)$o_value->{$vs_code})) {
										$va_tmp = explode(":", $vs_value);		//<item_id>:<item_idno>
										
										// TODONOW: create lists and list items if they don't already exist
										if ($vn_item_id = $this->lists->getItemIDFromList($t_element->get('list_id'), $va_tmp[1])) {
											$t_instance->replaceAttribute(
												array(
													$vs_code => $vn_item_id,
													'locale_id' => $vn_locale_id
												),
												$vs_code);
										}
									}
								}
							}
						}
						break;
					case __CA_ATTRIBUTE_VALUE_MEDIA__:
					case __CA_ATTRIBUTE_VALUE_FILE__:				
						$t_instance->update();
						if ($t_instance->numErrors()) {
							$this->logError("[{$vs_idno}] Could not update record before media: ".join('; ', $t_instance->getErrors()), $pa_options);
						}
						// TODO: detect if media has changes and only pull if it has
						$o_attr = $o_item->{'ca_attribute_'.$vs_code};
						foreach($o_attr as $va_tag => $o_tags) {
							foreach($o_tags as $vs_locale => $o_values) {
								if (!($vn_locale_id = $this->_createLocale($vs_locale))) { 
									throw new DataSynchronizerException(_t('Could not create locale %1', $vs_locale));
								}
								foreach($o_values as $o_value) {
									if ($vs_value = trim((string)$o_value->{$vs_code})) {
										$t_instance->replaceAttribute(
											array(
												$vs_code => $vs_value,		// value is URL
												'locale_id' => $vn_locale_id
											),
											$vs_code);
									}
								}
							}
						}
						$t_instance->update();
						if ($t_instance->numErrors()) {
							$this->logError("[{$vs_idno}] Could not update record after media: ".join('; ', $t_instance->getErrors()), $pa_options);
						}
						break;
					default:
						$o_attr = $o_item->{'ca_attribute_'.$vs_code};
						foreach($o_attr as $va_tag => $o_tags) {
							foreach($o_tags as $vs_locale => $o_values) {
								if (!($vn_locale_id = $this->_createLocale($vs_locale))) { 
									throw new DataSynchronizerException(_t('Could not create locale %1', $vs_locale));
								}
								foreach($o_values as $o_value) {
									if ($vs_value = trim((string)$o_value->{$vs_code})) {
									$t_instance->replaceAttribute(
										array(
											$vs_code => $vs_value,
											'locale_id' => $vn_locale_id
										),
										$vs_code);
									}
								}
							}
						}
						break;
				}	
			}
			
			// TODO: add hook onBeforeUpdate()
			$t_instance->update();
			// TODO: add hook onUpdate()
			
			if ($t_instance->numErrors()) {
				$this->logError("[{$vs_idno}] Could not update record: ".join('; ', $t_instance->getErrors()), $pa_options);
			}
						
			// get label fields
			$va_label_data = array();
			foreach($t_instance->getLabelUIFields() as $vs_field) {
				if (!($va_label_data[$vs_field] = $o_item->preferred_labels->{$vs_label_locale}->{$vs_field})) {
					$va_label_data[$vs_field] = $o_item->preferred_labels->{$vs_label_locale}->{$vs_field};
				}
			}
			
			// TODO: add hook onBeforeAddLabel()
			
			$t_instance->addLabel(
				$va_label_data, $vn_label_locale_id, null, true
			);
			// TODO: add hook onAddLabel()
			if ($t_instance->numErrors()) {
				$this->logError("[{$vs_idno}] Could not add label: ".join('; ', $t_instance->getErrors()), $pa_options);
			}
			
			$this->opa_processed_id_to_idno[$va_item['table'].'/'.(int)$va_item['id']] = $vs_idno;
			$this->opa_processed_records[$va_item['table'].'/'.(int)$va_item['id']] = $t_instance->getPrimaryKey();
			
			if ($vb_skip) { continue; }
			if (!(is_array($va_import_relationships_from))) { continue; }
			
			$pa_tables[$va_item['table']] = true;
			
			// Are there relationships?
			$pb_imported_self_relations = false;
			foreach($va_import_relationships_from as $vs_rel_table => $va_table_info) {
				$vb_is_self_relation = (($vs_rel_table == $vs_table) && (!$pb_imported_self_relations)) ? true : false;
				$va_rels = $this->datamodel->getRelationships($vs_table, $vs_rel_table);
				
				if (isset($va_rels[$vs_rel_table])) {
					// handle many-one relationships
					if ($vn_rel_id = (int)$o_item->{$va_rels[$vs_rel_table][$vs_table][0][0]}) {
						$va_queue = array(
							$vs_rel_table."/".$vn_id => array(
								'table' => $vs_rel_table,
								'id' => $vn_rel_id
							)
						);
						
						$this->fetchAndImport($va_queue, $po_client, $pa_config, $pa_tables, $ps_code, $pa_options);
						$t_instance->set($va_rels[$vs_rel_table][$vs_table][0][1], $this->opa_processed_id_to_idno[$vs_rel_table.'/'.$vn_rel_id]);
					}
				} elseif (!$pa_tables[$vs_rel_table] || $vb_is_self_relation) {
					// handle many-many relationships
					if (($vs_rel_table == $t_instance->tableName())) { $pb_imported_self_relations = true; }
					
					if ($o_item->{'related_'.$vs_rel_table}) {
						$t_rel = $this->datamodel->getInstanceByTableName($vs_rel_table, false);
						
						// TODO: add hook onBeforeAddRelationships()
						foreach($o_item->{'related_'.$vs_rel_table} as $vs_tag => $o_related_items) {
							foreach($o_related_items as $vs_i => $o_related_item) {
								if (is_array($pa_config['importRelatedFor'][$va_item['table']][$vs_rel_table])) {
									$va_restrict_to_relationship_types = $pa_config['importRelatedFor'][$va_item['table']][$vs_rel_table]['restrictToRelationshipTypes'];
									$va_rel_types = is_array($va_restrict_to_relationship_types) ? array_values($va_restrict_to_relationship_types) : array();
									if (is_array($va_rel_types) && sizeof($va_rel_types) && !in_array((string)$o_related_item->relationship_type_code, $va_rel_types)) {
										$this->logNotice("[{$vs_idno}] Skipped relationship for ".(string)$o_related_item->idno." because type='".(string)$o_related_item->relationship_type_code."' is excluded", $pa_options);
										continue;
									}
								}
								
								
								$vs_pk = $t_rel->primaryKey();
								$vn_id = (int)$o_related_item->{$vs_pk};
								$va_queue = array(
									$vs_rel_table."/".$vn_id => array(
										'table' => $vs_rel_table,
										'id' => $vn_id
									)
								);
								
								// TODO: Add from/until support	
								$va_restrict_to_bundles = $pa_config['importRelatedFor'][$va_item['table']][$vs_rel_table]['restrictToBundles'];
								$this->fetchAndImport($va_queue, $po_client, $pa_config, $pa_tables, $ps_code, array_merge($pa_options, array('restrictToBundles' => is_array($va_restrict_to_bundles) ? $va_restrict_to_bundles : null)));
								
								$vn_rel_record_id = $this->opa_processed_records[$vs_rel_table.'/'.(int)$vn_id];
								
								$vb_skip = false;
								if ($vb_is_self_relation) {
									if ( 
										(
											$this->opa_processed_self_relations[$vs_rel_table][$vn_rel_record_id][$t_instance->getPrimaryKey()][(string)$o_related_item->relationship_type_code] 
											|| 
											$this->opa_processed_self_relations[$vs_rel_table][$t_instance->getPrimaryKey()][$vn_rel_record_id][(string)$o_related_item->relationship_type_code]
										)
									) {  
										$vb_skip = true;
									} else { 
										$this->opa_processed_self_relations[$vs_rel_table][$t_instance->getPrimaryKey()][$vn_rel_record_id][(string)$o_related_item->relationship_type_code] = $this->opa_processed_self_relations[$vs_rel_table][$vn_rel_record_id][$t_instance->getPrimaryKey()][(string)$o_related_item->relationship_type_code] = true;
									}
								}
								
								if (!$vb_skip) {
									$t_instance->addRelationship($vs_rel_table, $vn_rel_record_id, (string)$o_related_item->relationship_type_code);
									if ($t_instance->numErrors()) {
										$this->logError("[{$vs_idno}] Could not add relationship to {$vs_rel_table} for row_id={$vn_rel_record_id}: ".join('; ', $t_instance->getErrors()), $pa_options);
									} 
								}
							}
						}
						
						// TODO: add hook onAddRelationships()
					}
				}
			}
			
			// TODO: make representation version fetched for replication configurable
			// Is there media?
			if (($t_instance instanceof RepresentableBaseModel) && ($t_instance->tableName() == 'ca_objects')) {	//TODO: generalize to all representable models
				try {
					$o_rep_xml = $po_client->getObjectRepresentations((int)$va_item['id'], array('original'))->get();
				} catch (exception $e) {
					$this->logError("[{$vs_idno}] While getting object representations: ".$e->getMessage(), $pa_options);
				}
				
				$va_existing_reps = $t_instance->getRepresentations(array('original'));
				$va_existing_md5s = array();
				$va_rep_ids = array();
				$va_dupe_reps = array();
				foreach($va_existing_reps as $va_rep) {
					if ($va_existing_md5s[$va_rep['info']['original']['MD5']]) {
						// dupe
						$va_dupe_reps[] = $va_rep['representation_id'];
						continue;
					}
					$va_existing_md5s[$va_rep['info']['original']['MD5']] = $va_rep['representation_id'];
					$va_rep_ids[] = $va_rep['representation_id'];
				}
		
				if ($o_rep_xml->getObjectRepresentations) {
					foreach($o_rep_xml->getObjectRepresentations as $vs_x => $o_reps) {
						foreach($o_reps as $vs_key => $o_rep) {
							if ($vs_url = trim((string)$o_rep->urls->original)) {
								$vs_remote_original_md5 = (string)$o_rep->info->original->MD5;

								if (((isset($va_existing_md5s[$vs_remote_original_md5]) && $va_existing_md5s[$vs_remote_original_md5]))) { 
									$this->logNotice("[{$vs_idno}] Skipping representation at {$vs_url} because it already exists (MD5={$vs_remote_original_md5}/{$vs_remote_large_md5}) ({$ps_code})", $pa_options);
									
									$vn_kill_rep_id = $va_existing_md5s[$vs_remote_original_md5];
						
									foreach($va_existing_md5s as $vs_md5 => $vn_rep_id) {
										if ($vn_kill_rep_id == $vn_rep_id) {
											$t_existing_rep_link = new ca_objects_x_object_representations();
											if ($t_existing_rep_link->load(array('object_id' => $t_instance->getPrimaryKey(), 'representation_id' => $vn_rep_id))) {
												$t_existing_rep_link->setMode(ACCESS_WRITE);
												$t_existing_rep_link->set('is_primary', (int)$o_rep->is_primary);
												$t_existing_rep_link->set('rank', (int)$o_rep->rank);
												$t_existing_rep_link->update();
												if ($t_existing_rep_link->numErrors()) {
													$this->logError("[{$vs_idno}] Could not update object-object representation relationship: ".join("; ", $t_existing_rep_link->getErrors())." ({$ps_code})", $pa_options);
												}
											}
											unset($va_existing_md5s[$vs_md5]);
										}
									}
									
									continue;
								}
								
								$this->logNotice("[{$vs_idno}] Importing for [{$vs_idno}] media from {$vs_url}: primary=".(string)$o_rep->is_primary." ({$ps_code})", $pa_options);
								
								// TODO: add hook onBeforeAddMedia()
								$vn_link_id = $t_instance->addRepresentation(
									$vs_url, $pn_rep_type_id, 1, (int)$o_rep->status, (int)$o_rep->access, (int)$o_rep->is_primary
								);
								
								// TODO: add hook onAddMedia()
								if ($t_instance->numErrors()) {
									$this->logError("[{$vs_idno}] Could not load object representation: ".join("; ", $t_instance->getErrors())." ({$ps_code})", $pa_options);
								} else {
									$t_link = new ca_objects_x_object_representations($vn_link_id);
									$t_new_rep = new ca_object_representations($t_link->get('representation_id'));
								}
							}
							
						}
					}
				}
				
				$va_rep_ids = array();
				foreach($va_existing_md5s as $vs_md5 => $vn_rep_id) {
					if ($va_rep_ids[$vn_rep_id]) { continue; }
					$t_obj_x_rep = new ca_objects_x_object_representations();
					while($t_obj_x_rep->load(array('object_id' => $t_instance->getPrimaryKey(), 'representation_id' => $vn_rep_id))) {
						$t_obj_x_rep->setMode(ACCESS_WRITE);
						$t_obj_x_rep->delete(true);
						
						if ($t_obj_x_rep->numErrors()) {
							$this->logError("[{$vs_idno}] Could not load remove object-to-representation link: ".join("; ", $t_obj_x_rep->getErrors())." ({$ps_code})", $pa_options);
							break;
						}
						
						if (!$t_obj_x_rep->load(array('representation_id' => $vn_rep_id))) {
							$t_rep = new ca_object_representations();
							if ($t_rep->load($vn_rep_id)) {
								$t_rep->setMode(ACCESS_WRITE);
								$t_rep->delete(true, array('hard' => true));
								if ($t_rep->numErrors()) {
									$this->logError("[{$vs_idno}] Could not remove representation: ".join("; ", $t_rep->getErrors()), $pa_options);
									break;
								}
							}
						}
					}
					$va_rep_ids[$vn_rep_id] = true;
				}
				
				foreach($va_dupe_reps as $vn_dupe_rep_id) {
					$t_rep = new ca_object_representations();
					if ($t_rep->load($vn_dupe_rep_id)) {
						$this->logNotice("[{$vs_idno}] Deleted duplicate representation {$vn_dupe_rep_id}", $pa_options);
						$t_rep->setMode(ACCESS_WRITE);
						$t_rep->delete(true, array('hard' => true));
						if ($t_rep->numErrors()) {
							$this->logError("[{$vs_idno}] Could not remove dupe representation: ".join("; ", $t_rep->getErrors()), $pa_options);
							break;
						}
					}
				}
				
			}
			unset($pa_tables[$va_item['table']]);
		}
		die("!!");
	}
	# ------------------------------------------------------------------
	/**
	 *
	 */
	private function _createLocale($ps_locale, $pa_options=null) {
		if ($vn_locale_id = $this->locale->localeCodeToID($ps_locale)) {
			// locale exists
			return $vn_locale_id;
		}
	
		$o_locale = new Zend_Locale($ps_locale);
		$vs_lang = $o_locale->getLanguage();
		$vs_region = $o_locale->getRegion();
		$vs_name = Zend_Locale::getTranslation($vs_lang, 'language', $vs_lang);
		
		$t_locale = new ca_locales();
		$t_locale->setMode(ACCESS_WRITE);
		$t_locale->set(array(
			'name' => $vs_name, 'language' => $vs_lang, 'country' => $vs_region,
			'dont_use_for_cataloguing' => 1
		));
		$t_locale->insert();
		
		if ($t_locale->numErrors()) {
			$this->logError(_t('Could not create new locale %1: %2', $ps_locale, join("; ", $t_locale->getErrors())), $pa_options);
			return null;
		}
		$this->logNotice(_t('Created new locale %1', $ps_locale), $pa_options);
		return $t_locale->getPrimaryKey();
		
	}
	# ------------------------------------------------------------------
	/**
	 *
	 */
	private function _createListItem($ps_list_code, $ps_idno, $pa_options=null) {		
		// check that item exists
		if (!($vn_item_id = caGetListItemID($ps_list_code, $ps_idno))) {
			// create item
			$t_list = new ca_lists();
			$t_list->load(array('list_code' => $ps_list_code));
			$t_item = $t_list->addItem($ps_idno, true, false, null, null, $ps_idno, '', 0, 0);
			// TODO: pull in full list item from service, check errors and generally do the right thing
			
			if ($t_item) { return $t_item->getPrimaryKey(); }
		}
		return $vn_item_id;
	}
	# ------------------------------------------------------------------
  }
  
  class DataSynchronizerException Extends Exception {
  	// noop
  }