<?php
/** ---------------------------------------------------------------------
 * tests/testsWithData/get/DisplayTemplateParserTest.php
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2015 Whirl-i-Gig
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
 * @subpackage tests
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */

require_once(__CA_BASE_DIR__.'/tests/testsWithData/BaseTestWithData.php');
require_once(__CA_LIB_DIR__.'/core/Parsers/DisplayTemplateParser.php');

class DisplayTemplateParserTest extends BaseTestWithData {
	# -------------------------------------------------------
	/**
	 * @var BundlableLabelableBaseModelWithAttributes
	 */
	private $opt_object = null;

	/**
	 * primary key ID of the last created entity
	 * @var int
	 */
	private $opn_entity_id = null;
	
	/**
	 * primary key ID of the first created object
	 * @var int
	 */
	private $opn_object_id = null;
	
	/**
	 * primary key ID of the last created object (the "related" object)
	 * @var int
	 */
	private $opn_rel_object_id = null;
	# -------------------------------------------------------
	public function setUp() {
		// don't forget to call parent so that the request is set up
		parent::setUp();

		/**
		 * @see http://docs.collectiveaccess.org/wiki/Web_Service_API#Creating_new_records
		 * @see https://gist.githubusercontent.com/skeidel/3871797/raw/item_request.json
		 */
		$vn_object_id = $this->opn_object_id = $this->addTestRecord('ca_objects', array(
			'intrinsic_fields' => array(
				'type_id' => 'image',
				'idno' => 'TEST.1'
			),
			'preferred_labels' => array(
				array(
					"locale" => "en_US",
					"name" => "My test image",
				),
			),
			'attributes' => array(
				// simple text
				'internal_notes' => array(
					array(
						'locale' => 'en_US',
						'internal_notes' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque ullamcorper sapien nec velit porta luctus.'
					)
				),
				'description' => array(
					array(
						'locale' => 'en_US',
						'description' => 'First description'
					),
					array(
						'locale' => 'en_US',
						'description' => 'Second description'
					),
					array(
						'locale' => 'en_US',
						'description' => 'Third description'
					)
				),
				// text in a container
				'external_link' => array(
					array(
						'url_source' => 'My URL source'
					),
					array(
						'url_source' => 'Another URL source'
					),
				),

				// Length
				'dimensions' => array(
					array(
						'dimensions_length' => '10 in',
						'dimensions_weight' => '2 lbs',
						'measurement_notes' => 'foo',
					),
				)
			)
		));
		$this->assertGreaterThan(0, $vn_object_id);
		$this->opt_object = new ca_objects($vn_object_id);
		
		$vn_rel_object_id = $this->opn_rel_object_id = $this->addTestRecord('ca_objects', array(
			'intrinsic_fields' => array(
				'type_id' => 'image',
				'idno' => 'TEST.2'
			),
			'preferred_labels' => array(
				array(
					"locale" => "en_US",
					"name" => "Another image",
				),
			),
			'related' => array(
				'ca_objects' => array(
					array(
						'object_id' => $vn_object_id,
						'type_id' => 'related'
					)
				),
			),
			'attributes' => array(
				// Length
				'dimensions' => array(
					array(
						'dimensions_length' => '1 in',
						'measurement_notes' => 'test',
					),
				)
			)
		));
		$this->assertGreaterThan(0, $vn_rel_object_id);
		
		$vn_entity_id = $this->addTestRecord('ca_entities', array(
			'intrinsic_fields' => array(
				'type_id' => 'ind',
				'idno' => 'hjs',
				'lifespan' => '12/17/1989 -'
			),
			'preferred_labels' => array(
				array(
					"locale" => "en_US",
					"forename" => "Homer",
					"middlename" => "J.",
					"surname" => "Simpson",
				),
			),
			'nonpreferred_labels' => array(
				array(
					"locale" => "en_US",
					"forename" => "Max",
					"middlename" => "",
					"surname" => "Power",
					"type_id" => "alt",
				),
			),
			'related' => array(
				'ca_objects' => array(
					array(
						'object_id' => $vn_object_id,
						'type_id' => 'creator',
						'effective_date' => '2015',
						'source_info' => 'Me'
					)
				),
			),
		));

		$this->assertGreaterThan(0, $vn_entity_id);

		$vn_entity_id = $this->addTestRecord('ca_entities', array(
			'intrinsic_fields' => array(
				'type_id' => 'ind',
				'idno' => 'bs',
			),
			'preferred_labels' => array(
				array(
					"locale" => "en_US",
					"forename" => "Bart",
					"middlename" => "",
					"surname" => "Simpson",
				),
			),
			'related' => array(
				'ca_objects' => array(
					array(
						'object_id' => $vn_object_id,
						'type_id' => 'publisher',
						'effective_date' => '2014-2015',
						'source_info' => 'Homer'
					)
				),
			),
		));

		$this->assertGreaterThan(0, $vn_entity_id);
		$this->opn_entity_id = $vn_entity_id;
	}
	# -------------------------------------------------------
	public function testBasicFieldsSingleRow() {
		// Get fields for primary rows (single row)
		$vm_ret = DisplayTemplateParser::evaluate("Name: ^ca_objects.preferred_labels.name (^ca_objects.idno)", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('Name: My test image (TEST.1)', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testBasicFieldsWithIfDefSingleRow() {
		// Get fields for primary rows with <ifdef> (single row)
		$vm_ret = DisplayTemplateParser::evaluate("Name: ^ca_objects.preferred_labels.name<ifdef code='ca_objects.idno'> (^ca_objects.idno)</ifdef>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('Name: My test image (TEST.1)', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testBasicFieldsWithIfNotDefSingleRow() {
		// Get fields for primary rows with <ifnotdef> (single row)
		$vm_ret = DisplayTemplateParser::evaluate("Name: ^ca_objects.preferred_labels.name<ifnotdef code='ca_objects.idno'> (^ca_objects.idno)</ifnotdef>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('Name: My test image', $vm_ret[0]);
	}	
	# -------------------------------------------------------
	public function testBasicAttributesSingleRowWithIfDef() {
		// Get fields for primary rows (single row)
		$vm_ret = DisplayTemplateParser::evaluate("<ifdef code='ca_objects.description'>Description: ^ca_objects.description", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('Description: First description;Second description;Third description', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testBasicFieldsMultipleRows() {
		// Get fields for primary rows (multiple rows)
		$vm_ret = DisplayTemplateParser::evaluate("Name: ^ca_objects.preferred_labels.name (^ca_objects.idno)", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(2, $vm_ret);
		$this->assertEquals('Name: My test image (TEST.1)', $vm_ret[0]);
		$this->assertEquals('Name: Another image (TEST.2)', $vm_ret[1]);
	}
	# -------------------------------------------------------
	public function testBasicFieldsWithIfDefMultipleRows() {	
		// Get fields for primary rows with <ifdef> (multiple row)
		$vm_ret = DisplayTemplateParser::evaluate("Name: ^ca_objects.preferred_labels.name<ifdef code='ca_objects.idno'> (^ca_objects.idno)</ifdef>", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(2, $vm_ret);
		$this->assertEquals('Name: My test image (TEST.1)', $vm_ret[0]);
		$this->assertEquals('Name: Another image (TEST.2)', $vm_ret[1]);
	}
	# -------------------------------------------------------
	public function testBasicFieldsWithIfNotDefMultipleRows() {	
		// Get fields for primary rows with <ifnotdef> (multiple row)
		$vm_ret = DisplayTemplateParser::evaluate("Name: ^ca_objects.preferred_labels.name<ifnotdef code='ca_objects.idno'> (^ca_objects.idno)</ifnotdef>", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(2, $vm_ret);
		$this->assertEquals('Name: My test image', $vm_ret[0]);
		$this->assertEquals('Name: Another image', $vm_ret[1]);
	}
	# -------------------------------------------------------
	public function testBasicFormatWithRelated() {	
		// Get related values
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.preferred_labels.name (^ca_objects.idno) => ^ca_entities.preferred_labels.displayname", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('My test image (TEST.1) => Homer J. Simpson;Bart Simpson', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testBasicFormatWithRelatedAndTagOpts() {	
		// Get related values with tag-option delimiter
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.preferred_labels.name (^ca_objects.idno) => ^ca_entities.preferred_labels.displayname%delimiter=,_", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('My test image (TEST.1) => Homer J. Simpson, Bart Simpson', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testUnitsWithRelatedValues() {
		// Get related values in <unit>
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.preferred_labels.name (^ca_objects.idno) => <unit relativeTo='ca_entities' delimiter=', '>^ca_entities.preferred_labels.displayname (^ca_entities.lifespan)</unit>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('My test image (TEST.1) => Homer J. Simpson (after December 17 1989), Bart Simpson ()', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testUnitsWithRelatedValuesAndIfDef() {		
		// Get related values in <unit> with <ifdef>
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.preferred_labels.name (^ca_objects.idno) => <unit relativeTo='ca_entities' delimiter=', '>^ca_entities.preferred_labels.displayname<ifdef code='ca_entities.lifespan'> (^ca_entities.lifespan)</ifdef></unit>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('My test image (TEST.1) => Homer J. Simpson (after December 17 1989), Bart Simpson', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testUnitsWithRelatedValuesAndIfDefAndRestrictToRelationshipTypes() {		
		// Get related values in <unit> with <ifdef> and restrictToRelationshipTypes
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.preferred_labels.name (^ca_objects.idno) => <unit relativeTo='ca_entities' restrictToRelationshipTypes='creator' delimiter=', '>^ca_entities.preferred_labels.displayname<ifdef code='ca_entities.lifespan'> (^ca_entities.lifespan)</ifdef></unit>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('My test image (TEST.1) => Homer J. Simpson (after December 17 1989)', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testUnitsWithRelatedValuesAndIfDefAndMultRestrictToRelationshipTypes() {		
		// Get related values in <unit> with <ifdef> and restrictToRelationshipTypes
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.preferred_labels.name (^ca_objects.idno) => <unit relativeTo='ca_entities' restrictToRelationshipTypes='creator,publisher' delimiter=', '>^ca_entities.preferred_labels.displayname<ifdef code='ca_entities.lifespan'> (^ca_entities.lifespan)</ifdef></unit>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('My test image (TEST.1) => Homer J. Simpson (after December 17 1989), Bart Simpson', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testNestedUnits() {
		// Get related values in <unit>
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.preferred_labels.name (^ca_objects.idno) => <unit relativeTo='ca_entities' delimiter=', '>^ca_entities.preferred_labels.displayname (^ca_entities.lifespan) <unit relativeTo='ca_objects'>[Back to ^ca_objects.preferred_labels.name]</unit></unit>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('My test image (TEST.1) => Homer J. Simpson (after December 17 1989) [Back to My test image], Bart Simpson () [Back to My test image]', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testNestedUnitsWithIfDef() {
		// Get related values in <unit> with <ifdef>
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.preferred_labels.name (^ca_objects.idno) => <unit relativeTo='ca_objects.related' delimiter=', '>^ca_objects.preferred_labels.name</unit>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('My test image (TEST.1) => Another image', $vm_ret[0]);
	}	
	# -------------------------------------------------------
	public function testFormatsWithIfCount() {
		// <ifcount>
		$vm_ret = DisplayTemplateParser::evaluate("<ifcount code='ca_entities' min='0' max='2'>^ca_entities.preferred_labels.displayname</ifcount>", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true, 'delimiter' => ', '));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('Homer J. Simpson, Bart Simpson', $vm_ret[0]);
		
		// <ifcount>
		$vm_ret = DisplayTemplateParser::evaluate("<ifcount code='ca_entities' min='1' max='1'>^ca_entities.preferred_labels.displayname</ifcount>", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true, 'delimiter' => ', '));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(0, $vm_ret);
	}
	# -------------------------------------------------------
	public function testFormatsWithIfCountAndRestrictToRelationshipTypes() {
		// <ifcount> with restrictToRelationshipTypes
		$vm_ret = DisplayTemplateParser::evaluate("<ifcount restrictToRelationshipTypes='publisher' code='ca_entities' min='1' max='1'>^ca_entities.preferred_labels.displayname%restrictToRelationshipTypes=publisher</ifcount>", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true, 'delimiter' => ', '));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
	}
	# -------------------------------------------------------
	public function testFormatsWithIfCountAndIncludeBlanks() {
		// <ifcount>
		$vm_ret = DisplayTemplateParser::evaluate("<ifcount code='ca_entities' min='1' max='1'>^ca_entities.preferred_labels.displayname</ifcount>", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true, 'delimiter' => ', ', 'includeBlankValuesInArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(2, $vm_ret);
		
		// <ifcount> with restrictToRelationshipTypes
		$vm_ret = DisplayTemplateParser::evaluate("<ifcount restrictToRelationshipTypes='publisher' code='ca_entities' min='1' max='1'>^ca_entities.preferred_labels.displayname%restrictToRelationshipTypes=publisher</ifcount>", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true, 'delimiter' => ', ', 'includeBlankValuesInArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(2, $vm_ret);
	}
	# -------------------------------------------------------
	public function testFormatsWithCase() {
		$vm_ret = DisplayTemplateParser::evaluate("
			<case>
				<ifcount code='ca_entities' min='1' max='1'>^ca_entities.preferred_labels.displayname</ifcount>
				<ifnotdef code='ca_objects.description'>Description was not set</ifnotdef>
				<ifdef code='ca_objects.idno'>Idno was ^ca_objects.idno</ifdef>
				<ifdef code='ca_objects.preferred_labels.name'>Label was ^ca_objects.preferred_labels.name</ifdef>
			</case>
		", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true, 'delimiter' => ', ', 'includeBlankValuesInArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('Idno was TEST.1', trim($vm_ret[0]));		// <case> includes whitespace we need to get rid of for comparison
		
		
		$vm_ret = DisplayTemplateParser::evaluate("
			<case>
				<ifcount code='ca_entities' min='1' max='1'>^ca_entities.preferred_labels.displayname</ifcount>
				<ifnotdef code='ca_objects.description'>Description was not set</ifnotdef>
				<ifdef code='ca_objects.preferred_labels.name'>Label was ^ca_objects.preferred_labels.name</ifdef>
				<ifdef code='ca_objects.idno'>Idno was ^ca_objects.idno</ifdef>
			</case>
		", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true, 'delimiter' => ', ', 'includeBlankValuesInArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('Label was My test image', trim($vm_ret[0]));	// <case> includes whitespace we need to get rid of for comparison
	}
	# -------------------------------------------------------
	public function testFormatWithCaseDefault() {
		$vm_ret = DisplayTemplateParser::evaluate("
			<case>
				<ifcount code='ca_entities' min='1' max='1'>^ca_entities.preferred_labels.displayname</ifcount>
				<ifnotdef code='ca_objects.description'>Description was not set</ifnotdef>
				<unit>Default ^ca_objects.idno</unit>
			</case>
		", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true, 'delimiter' => ', ', 'includeBlankValuesInArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('Default TEST.1', trim($vm_ret[0]));	// <case> includes whitespace we need to get rid of for comparison
	}
	# -------------------------------------------------------
	public function testFormatsWithHTML() {
		// Get fields for primary rows (single row)
		$vm_ret = DisplayTemplateParser::evaluate("Name: <b>^ca_objects.preferred_labels.name</b> (^ca_objects.idno)", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertEquals('Name: <b>My test image</b> (TEST.1)', $vm_ret[0]);
	}
	# -------------------------------------------------------
	//public function testFormatsWithSort() {
		// TODO: add sort and sortDirection options to parser
	//}
	# -------------------------------------------------------
	public function testFormatsWithSkipIfExpressionOption() {
		$vm_ret = DisplayTemplateParser::evaluate("Name: <b>^ca_objects.preferred_labels.name</b> (^ca_objects.idno)", "ca_objects", array($this->opn_object_id), array('skipIfExpression' => '^ca_objects.description =~ /First/', 'returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(0, $vm_ret);
		
		$vm_ret = DisplayTemplateParser::evaluate("Name: <b>^ca_objects.preferred_labels.name</b> (^ca_objects.idno)", "ca_objects", array($this->opn_object_id), array('skipIfExpression' => '^ca_objects.description =~ /NICHTS/', 'returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Name: <b>My test image</b> (TEST.1)', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testFormatsWithRequireLinkTagsOption() {
		$vm_ret = DisplayTemplateParser::evaluate("URL: <l>^ca_objects.preferred_labels.name</l> (^ca_objects.idno)", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertContains("editor/objects/ObjectEditor/Summary/object_id/{$this->opn_object_id}/rel/1'>My test image</a> (TEST.1)", $vm_ret[0]);
		
		$vm_ret = DisplayTemplateParser::evaluate("URL: ^ca_objects.preferred_labels.name (^ca_objects.idno)", "ca_objects", array($this->opn_object_id), array('requireLinkTags' => false, 'returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertContains("editor/objects/ObjectEditor/Summary/object_id/{$this->opn_object_id}'>URL: My test image (TEST.1)</a>", $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testFormatsWithPlaceholderPrefixOption() {
		$vm_ret = DisplayTemplateParser::evaluate("URL: <b>^url_source</b> (^ca_objects.idno)", "ca_objects", array($this->opn_object_id), array('placeholderPrefix' => 'ca_objects.external_link', 'returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('URL: <b>My URL source;Another URL source</b> (TEST.1)', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testFormatsWithBetween() {
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.dimensions.dimensions_length <between>X</between> ^ca_objects.dimensions.dimensions_weight", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(2, $vm_ret);
		$this->assertEquals('10.0 in X 2.0000 lb', $vm_ret[0]);
		$this->assertEquals('1.0 in', trim($vm_ret[1]));
		
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.dimensions.dimensions_weight <between>X</between> ^ca_objects.dimensions.dimensions_length", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(2, $vm_ret);
		$this->assertEquals('2.0000 lb X 10.0 in', $vm_ret[0]);
		$this->assertEquals('1.0 in', trim($vm_ret[1]));
	}
	# -------------------------------------------------------
	public function testFormatsWithMore() {
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.dimensions.dimensions_length <more>X</more> ^ca_objects.dimensions.dimensions_weight", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(2, $vm_ret);
		$this->assertEquals('10.0 in X 2.0000 lb', $vm_ret[0]);
		$this->assertEquals('1.0 in', trim($vm_ret[1]));
		
		$vm_ret = DisplayTemplateParser::evaluate("^ca_objects.dimensions.dimensions_weight <more>X</more> ^ca_objects.dimensions.dimensions_length", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(2, $vm_ret);
		$this->assertEquals('2.0000 lb X 10.0 in', $vm_ret[0]);
		$this->assertEquals('X 1.0 in', trim($vm_ret[1]));
	}
	# -------------------------------------------------------
	public function testFormatsWithIf() {
		$vm_ret = DisplayTemplateParser::evaluate("Description: <if rule='^ca_objects.description =~ /First/'>^ca_objects.description%delimiter=,_</if>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Description: First description, Second description, Third description', $vm_ret[0]);
		
		$vm_ret = DisplayTemplateParser::evaluate("Description: <if rule='^ca_objects.description =~ /Fourth/'>^ca_objects.description%delimiter=,_</if>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Description: ', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testFormatsWithExpression() {
		$vm_ret = DisplayTemplateParser::evaluate("Expression: word count is <expression>wc(^ca_objects.description)</expression>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Expression: word count is 6', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testFormatsWithBareUnit() {
		$vm_ret = DisplayTemplateParser::evaluate("Here are the descriptions: <unit delimiter='... '>^ca_objects.description</unit>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Here are the descriptions: First description... Second description... Third description', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testFormatsWithTagOpts() {
		$vm_ret = DisplayTemplateParser::evaluate("Here are the descriptions: ^ca_objects.description%delimiter=,_", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Here are the descriptions: First description, Second description, Third description', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testFormatsWithDate() {
		$vs_date = date('d M Y'); // you execute this test right at the stroke of midnight it might fail...
		$vm_ret = DisplayTemplateParser::evaluate("The current date is ^DATE. This has nothing to do with object ^ca_objects.idno", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals("The current date is {$vs_date}. This has nothing to do with object TEST.1", $vm_ret[0]);
		
		$vs_date = date('M:d:Y'); // you execute this test right at the stroke of midnight it might fail...
		$vm_ret = DisplayTemplateParser::evaluate("The current date is ^DATE%format=M:d:Y. This has nothing to do with object ^ca_objects.idno", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals("The current date is {$vs_date}. This has nothing to do with object TEST.1", $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testIfDefWithOrCodes() {
		$vm_ret = DisplayTemplateParser::evaluate("<ifdef code='ca_objects.formatNotes|ca_objects.description'>Value was detected</ifdef>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Value was detected', $vm_ret[0]);
		
		$vm_ret = DisplayTemplateParser::evaluate("<ifdef code='ca_objects.formatNotes;ca_objects.description'>Value was detected</ifdef>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(0, $vm_ret);
		
		$vm_ret = DisplayTemplateParser::evaluate("<ifdef code='ca_objects.preferred_labels.name;ca_objects.idno;ca_objects.description'>Value was detected</ifdef>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Value was detected', $vm_ret[0]);
		
		$vm_ret = DisplayTemplateParser::evaluate("<ifdef code='ca_objects.preferred_labels.name;ca_objects.idno;ca_objects.description'>Value was detected</ifdef>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Value was detected', $vm_ret[0]);
		
		$vm_ret = DisplayTemplateParser::evaluate("<ifnotdef code='ca_objects.preferred_labels.name;ca_objects.idno;ca_objects.description'>Value was detected</ifdef>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(0, $vm_ret);
		
		$vm_ret = DisplayTemplateParser::evaluate("<ifnotdef code='ca_objects.formatNotes,ca_objects.description'>Value was detected</ifdef>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(0, $vm_ret);
		
		$vm_ret = DisplayTemplateParser::evaluate("<ifnotdef code='ca_objects.formatNotes|ca_objects.description'>Value was detected</ifdef>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Value was detected', $vm_ret[0]);
	}
	# -------------------------------------------------------
	public function testIfCountWithOrCodes() {
		$vm_ret = DisplayTemplateParser::evaluate("<ifcount code='ca_objects.description|ca_objects.preferred_labels.name' min='1' max='4'>Value was detected</ifcount>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(1, $vm_ret);
		$this->assertEquals('Value was detected', $vm_ret[0]);
		
		$vm_ret = DisplayTemplateParser::evaluate("<ifcount code='ca_objects.formatNotes|ca_objects.preferred_labels.name' min='1' max='4'>Value was detected</ifcount>", "ca_objects", array($this->opn_object_id), array('returnAsArray' => true));
		$this->assertInternalType('array', $vm_ret);
		$this->assertCount(0, $vm_ret);
	}
	# -------------------------------------------------------
	public function testFormatsAsString() {
		// Get fields for primary rows with <ifnotdef> (multiple row)
		$vm_ret = DisplayTemplateParser::evaluate("Name: ^ca_objects.preferred_labels.name<ifnotdef code='ca_objects.idno'> (^ca_objects.idno)</ifnotdef>", "ca_objects", array($this->opn_object_id, $this->opn_rel_object_id), array('returnAsArray' => false));
		$this->assertInternalType('string', $vm_ret);
		$this->assertEquals('Name: My test image; Name: Another image', $vm_ret);
	}
	# -------------------------------------------------------
}
