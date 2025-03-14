<?php

namespace SilverStripe\Forms\Tests\GridField;

use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\Tests\GridField\GridFieldSortableHeaderTest\Cheerleader;
use SilverStripe\Forms\Tests\GridField\GridFieldSortableHeaderTest\CheerleaderHat;
use SilverStripe\Forms\Tests\GridField\GridFieldSortableHeaderTest\Team;
use SilverStripe\Forms\Tests\GridField\GridFieldSortableHeaderTest\TeamGroup;
use SilverStripe\Forms\Tests\GridField\GridFieldSortableHeaderTest\Mom;
use SilverStripe\ORM\DataList;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridField;

class GridFieldSortableHeaderTest extends SapphireTest
{

    protected static $fixture_file = 'GridFieldSortableHeaderTest.yml';

    protected static $extra_dataobjects = [
        Team::class,
        TeamGroup::class,
        Cheerleader::class,
        CheerleaderHat::class,
        Mom::class,
    ];

    /**
     * Tests that the appropriate sortable headers are generated
     *
     * @skipUpgrade
     */
    public function testRenderHeaders()
    {

        // Generate sortable header and extract HTML
        $list = new DataList(Team::class);
        $config = new GridFieldConfig_RecordEditor();
        $form = new Form(null, 'Form', new FieldList(), new FieldList());
        $gridField = new GridField('testfield', 'testfield', $list, $config);
        $gridField->setForm($form);
        $component = $gridField->getConfig()->getComponentByType(GridFieldSortableHeader::class);
        $htmlFragment = $component->getHTMLFragments($gridField);

        // Check that the output shows name and hat as sortable fields, but not city
        $this->assertStringContainsString('<span class="non-sortable">City</span>', $htmlFragment['header']);
        $this->assertStringContainsString(
            'value="Name" class="action grid-field__sort" id="action_SetOrderName"',
            $htmlFragment['header']
        );
        $this->assertStringContainsString(
            'value="Cheerleader Hat" class="action grid-field__sort" id="action_SetOrderCheerleader-Hat-Colour"',
            $htmlFragment['header']
        );

        // Check inverse of above
        $this->assertStringNotContainsString(
            'value="City" class="action grid-field__sort" id="action_SetOrderCity"',
            $htmlFragment['header']
        );
        $this->assertStringNotContainsString('<span class="non-sortable">Name</span>', $htmlFragment['header']);
        $this->assertStringNotContainsString('<span class="non-sortable">Cheerleader Hat</span>', $htmlFragment['header']);
    }

    public function testGetManipulatedData()
    {
        $list = Team::get()->filter([ 'ClassName' => Team::class ]);
        $config = new GridFieldConfig_RecordEditor();
        $gridField = new GridField('testfield', 'testfield', $list, $config);
        $component = $gridField->getConfig()->getComponentByType(GridFieldSortableHeader::class);

        // Test normal sorting
        $component->setFieldSorting(['Name' => 'City']);
        $state = $gridField->State->GridFieldSortableHeader;
        $state->SortColumn = 'City';
        $state->SortDirection = 'asc';

        $listA = $component->getManipulatedData($gridField, $list);

        $state->SortDirection = 'desc';
        $listB = $component->getManipulatedData($gridField, $list);

        $this->assertEquals(
            ['Auckland', 'Cologne', 'Melbourne', 'Wellington'],
            $listA->column('City')
        );
        $this->assertEquals(
            ['Wellington', 'Melbourne', 'Cologne', 'Auckland'],
            $listB->column('City')
        );

        // Test one relation 'deep'
        $component->setFieldSorting(['Name' => 'Cheerleader.Name']);
        $state->SortColumn = 'Cheerleader.Name';
        $state->SortDirection = 'asc';
        $relationListA = $component->getManipulatedData($gridField, $list);

        $state->SortDirection = 'desc';
        $relationListB = $component->getManipulatedData($gridField, $list);

        $this->assertEquals(
            ['Wellington', 'Melbourne', 'Cologne', 'Auckland'],
            $relationListA->column('City')
        );
        $this->assertEquals(
            ['Auckland', 'Cologne', 'Melbourne', 'Wellington'],
            $relationListB->column('City')
        );

        // Test two relations 'deep'
        $component->setFieldSorting(['Name' => 'Cheerleader.Hat.Colour']);
        $state->SortColumn = 'Cheerleader.Hat.Colour';
        $state->SortDirection = 'asc';
        $relationListC = $component->getManipulatedData($gridField, $list);

        $state->SortDirection = 'desc';
        $relationListD = $component->getManipulatedData($gridField, $list);

        $this->assertEquals(
            ['Cologne', 'Auckland', 'Wellington', 'Melbourne'],
            $relationListC->column('City')
        );
        $this->assertEquals(
            ['Melbourne', 'Wellington', 'Auckland', 'Cologne'],
            $relationListD->column('City')
        );
    }

    /**
     * Test getManipulatedData on subclassed dataobjects
     */
    public function testInheritedGetManiplatedData()
    {
        $list = TeamGroup::get();
        $config = new GridFieldConfig_RecordEditor();
        $gridField = new GridField('testfield', 'testfield', $list, $config);
        $state = $gridField->State->GridFieldSortableHeader;
        $component = $gridField->getConfig()->getComponentByType(GridFieldSortableHeader::class);

        // Test that inherited dataobjects will work correctly
        $component->setFieldSorting(['Name' => 'Cheerleader.Hat.Colour']);
        $state->SortColumn = 'Cheerleader.Hat.Colour';
        $state->SortDirection = 'asc';
        $relationListA = $component->getManipulatedData($gridField, $list);
        $relationListAsql = Convert::nl2os($relationListA->sql(), ' ');

        // Assert that all tables are joined properly
        $this->assertStringContainsString('FROM "GridFieldSortableHeaderTest_Team"', $relationListAsql);
        $this->assertStringContainsString(
            'LEFT JOIN "GridFieldSortableHeaderTest_TeamGroup" '
            . 'ON "GridFieldSortableHeaderTest_TeamGroup"."ID" = "GridFieldSortableHeaderTest_Team"."ID"',
            $relationListAsql
        );
        $this->assertStringContainsString(
            'LEFT JOIN "GridFieldSortableHeaderTest_Cheerleader" '
            . 'AS "cheerleader_GridFieldSortableHeaderTest_Cheerleader" '
            . 'ON "cheerleader_GridFieldSortableHeaderTest_Cheerleader"."ID" = '
            . '"GridFieldSortableHeaderTest_Team"."CheerleaderID"',
            $relationListAsql
        );
        $this->assertStringContainsString(
            'LEFT JOIN "GridFieldSortableHeaderTest_CheerleaderHat" '
            . 'AS "cheerleader_hat_GridFieldSortableHeaderTest_CheerleaderHat" '
            . 'ON "cheerleader_hat_GridFieldSortableHeaderTest_CheerleaderHat"."ID" = '
            . '"cheerleader_GridFieldSortableHeaderTest_Cheerleader"."HatID"',
            $relationListAsql
        );

        // Test sorting is correct
        $this->assertEquals(
            ['Cologne', 'Auckland', 'Wellington', 'Melbourne'],
            $relationListA->column('City')
        );
        $state->SortDirection = 'desc';
        $relationListAdesc = $component->getManipulatedData($gridField, $list);
        $this->assertEquals(
            ['Melbourne', 'Wellington', 'Auckland', 'Cologne'],
            $relationListAdesc->column('City')
        );

        // Test subclasses of tables
        $component->setFieldSorting(['Name' => 'CheerleadersMom.Hat.Colour']);
        $state->SortColumn = 'CheerleadersMom.Hat.Colour';
        $state->SortDirection = 'asc';
        $relationListB = $component->getManipulatedData($gridField, $list);
        $relationListBsql = $relationListB->sql();

        // Assert that subclasses are included in the query
        $this->assertStringContainsString('FROM "GridFieldSortableHeaderTest_Team"', $relationListBsql);
        $this->assertStringContainsString(
            'LEFT JOIN "GridFieldSortableHeaderTest_TeamGroup" '
            . 'ON "GridFieldSortableHeaderTest_TeamGroup"."ID" = "GridFieldSortableHeaderTest_Team"."ID"',
            $relationListBsql
        );
        // Joined tables are joined basetable first
        // Note: CheerLeader is base of Mom table, hence the alias
        $this->assertStringContainsString(
            'LEFT JOIN "GridFieldSortableHeaderTest_Cheerleader" '
            . 'AS "cheerleadersmom_GridFieldSortableHeaderTest_Cheerleader" '
            . 'ON "cheerleadersmom_GridFieldSortableHeaderTest_Cheerleader"."ID" = '
            . '"GridFieldSortableHeaderTest_Team"."CheerleadersMomID"',
            $relationListBsql
        );
        // Then the basetable of the joined record is joined to the specific subtable
        $this->assertStringContainsString(
            'LEFT JOIN "GridFieldSortableHeaderTest_Mom" '
            . 'AS "cheerleadersmom_GridFieldSortableHeaderTest_Mom" '
            . 'ON "cheerleadersmom_GridFieldSortableHeaderTest_Cheerleader"."ID" = '
            . '"cheerleadersmom_GridFieldSortableHeaderTest_Mom"."ID"',
            $relationListBsql
        );
        $this->assertStringContainsString(
            'LEFT JOIN "GridFieldSortableHeaderTest_CheerleaderHat" '
            . 'AS "cheerleadersmom_hat_GridFieldSortableHeaderTest_CheerleaderHat" '
            . 'ON "cheerleadersmom_hat_GridFieldSortableHeaderTest_CheerleaderHat"."ID" = '
            . '"cheerleadersmom_GridFieldSortableHeaderTest_Cheerleader"."HatID"',
            $relationListBsql
        );


        // Test sorting is correct
        $this->assertEquals(
            ['Cologne', 'Auckland', 'Wellington', 'Melbourne'],
            $relationListB->column('City')
        );
        $state->SortDirection = 'desc';
        $relationListBdesc = $component->getManipulatedData($gridField, $list);
        $this->assertEquals(
            ['Melbourne', 'Wellington', 'Auckland', 'Cologne'],
            $relationListBdesc->column('City')
        );
    }

    public function testSortColumnValidation()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid SortColumn: INVALID');

        $list = Team::get()->filter([ 'ClassName' => Team::class ]);
        $config = new GridFieldConfig_RecordEditor();
        $gridField = new GridField('testfield', 'testfield', $list, $config);
        $component = $gridField->getConfig()->getComponentByType(GridFieldSortableHeader::class);

        $state = $gridField->State->GridFieldSortableHeader;
        $state->SortColumn = 'INVALID';
        $state->SortDirection = 'asc';

        $component->getManipulatedData($gridField, $list);
    }
}
