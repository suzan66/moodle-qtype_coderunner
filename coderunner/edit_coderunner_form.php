<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the editing form for the coderunner question type.
 *
 * @package 	questionbank
 * @subpackage 	questiontypes
 * @copyright 	&copy; 2013 Richard Lobb
 * @author 		Richard Lobb richard.lobb@canterbury.ac.nz
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
define("NUM_TESTCASES_START", 5); // Num empty test cases with new questions
define("NUM_TESTCASES_ADD", 3);   // Extra empty test cases to add
define("DEFAULT_NUM_ROWS", 18);   // Answer box rows
define("DEFAULT_NUM_COLS", 100);   // Answer box rows

require_once($CFG->dirroot . '/question/type/coderunner/Sandbox/sandbox_config.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

/**
 * coderunner editing form definition.
 */
class qtype_coderunner_edit_form extends question_edit_form {

    /**testcode
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    var $_textarea_or_htmleditor_generalfb;   //addElement type for general feedback
    var $_editor_options_generalfb;           //in dependence of editor type set a different array for its options

    function qtype() {
        return 'coderunner';
    }


    protected function definition() {
        // Override to add my coderunner_type selector at the top.
        // Need to support both JavaScript enabled and JavaScript disabled
        // sites, so there's a normal select item, containing all languages
        // and question types, for a default and a YUI3 hierarchical menu
        // that the JavaScript switches on if it's enabled.
        // [But it's currently not being used as it looks horrible. Needs
        // better CSS or a rethink.]
        global $PAGE;
        global $SANDBOXES;
        $jsmodule = array(
            'name'      => 'qtype_coderunner',
            'fullpath'  => '/question/type/coderunner/module.js',
            'requires'  => array('base', 'widget', 'io', 'node-menunav')
        );

        $mform = $this->_form;
        $question = $this->question;

        list($languages, $types) = $this->get_languages_and_types();

        $mform->addElement('header', 'questiontypeheader', get_string('type_header','qtype_coderunner'));

        $typeSelectorElements = array();
        $expandedTypes = array_merge(array('Undefined' => 'Undefined'), $types);
        $typeSelectorElements[] = $mform->createElement('select', 'coderunner_type',
                NULL, $expandedTypes);

        // Fancy YUI type menu disabled for now as it looked ugly. To re-enable
        // uncomment the following statement and add the line 'this.useYuiTypesMenu(Y);'
        // to the init() function in module.js.
        // $typeControls =& $mform->createElement('html', $this->makeYuiMenu(array_keys($languages), array_keys($types)));

        $typeSelectorElements[] = $mform->createElement('advcheckbox', 'customise', NULL,
                get_string('customise', 'qtype_coderunner'));
        $typeSelectorElements[] = $mform->createElement('advcheckbox', 'show_source', NULL,
                get_string('show_source', 'qtype_coderunner'));

        $mform->addElement('group', 'coderunner_type_group',
                get_string('questiontype', 'qtype_coderunner'), $typeSelectorElements, NULL, false);
        $mform->setDefault('show_source', False);
        $mform->addHelpButton('coderunner_type_group', 'coderunner_type', 'qtype_coderunner');

        $answerboxElements = array();
        $answerboxElements[] = $mform->createElement('text', 'answerbox_lines',
                get_string('answerbox_lines', 'qtype_coderunner'),
                array('size'=>3));
        $mform->setType('answerbox_lines', PARAM_INT);
        $mform->setDefault('answerbox_lines', DEFAULT_NUM_ROWS);
        $answerboxElements[] = $mform->createElement('text', 'answerbox_columns',
                get_string('answerbox_columns', 'qtype_coderunner'),
                array('size'=>3));
        $mform->setType('answerbox_columns', PARAM_INT);
        $mform->setDefault('answerbox_columns', DEFAULT_NUM_COLS);
        $answerboxElements[] = $mform->createElement('advcheckbox', 'use_ace', NULL,
                get_string('use_ace', 'qtype_coderunner'));
        $mform->setDefault('use_ace', True);
        $mform->addElement('group', 'answerbox_group', get_string('answerbox_group', 'qtype_coderunner'),
                $answerboxElements, NULL, false);
        $mform->addHelpButton('answerbox_group', 'answerbox_group', 'qtype_coderunner');

        $mform->addElement('advcheckbox', 'all_or_nothing', get_string('marking', 'qtype_coderunner'),
                get_string('all_or_nothing', 'qtype_coderunner'));
        $mform->setDefault('all_or_nothing', True);
        $mform->addHelpButton('all_or_nothing', 'all_or_nothing', 'qtype_coderunner');

        $mform->addElement('text', 'penalty_regime',
            get_string('penalty_regime', 'qtype_coderunner'),
            array('size' => 20));
        $mform->addHelpButton('penalty_regime', 'penalty_regime', 'qtype_coderunner');
        $mform->setType('penalty_regime', PARAM_RAW);


        // The following fields are used to customise a question by overriding
        // values from the base question type. All are hidden
        // unless the 'customise' checkbox is checked.

        $mform->addElement('header', 'customisationheader',
                get_string('customisation','qtype_coderunner'));

        $mform->addElement('textarea', 'per_test_template',
                get_string('template', 'qtype_coderunner'),
                array('rows'=>8, 'cols'=>80, 'class'=>'template edit_code',
                      'name'=>'per_test_template'));


        $mform->addHelpButton('per_test_template', 'template', 'qtype_coderunner');
        $gradingControls = array();
        $graderTypes = array('EqualityGrader' => 'Exact match',
                'NearEqualityGrader' => 'Nearly exact match',
                'RegexGrader' => 'Regular expression',
                'TemplateGrader' => 'Template does grading');
        $gradingControls[] = $mform->createElement('select', 'grader',
                NULL, $graderTypes);
        $mform->addElement('group', 'gradingcontrols',
                get_string('grading', 'qtype_coderunner'), $gradingControls,
                NULL, false);
        $mform->addHelpButton('gradingcontrols', 'gradingcontrols', 'qtype_coderunner');

        $columnControls = array();
        $columnControls[] =& $mform->createElement('advcheckbox', 'showtest', NULL,
                get_string('show_test', 'qtype_coderunner'));
        $columnControls[] =& $mform->createElement('advcheckbox', 'showstdin', NULL,
                get_string('show_stdin', 'qtype_coderunner'));
        $columnControls[] =& $mform->createElement('advcheckbox', 'showexpected', NULL,
                get_string('show_expected', 'qtype_coderunner'));
        $columnControls[] =& $mform->createElement('advcheckbox', 'showoutput', NULL,
                get_string('show_output', 'qtype_coderunner'));
        $columnControls[] =& $mform->createElement('advcheckbox', 'showmark', NULL,
                get_string('show_mark', 'qtype_coderunner'));
        foreach (array('showtest', 'showstdin', 'showexpected', 'showoutput') as $control) {
            $mform->setDefault($control, True);
        }
        $mform->setDefault('showmark', False);

        $mform->addElement('group', 'columncontrols',
                get_string('columncontrols', 'qtype_coderunner'),
                $columnControls,NULL, false);
        $mform->addHelpButton('columncontrols', 'columncontrols', 'qtype_coderunner');


        // The next set of fields are also used to customise a question by overriding
        // values from the base question type, but these ones are much more
        // advanced and not recommended for most users. They too are hidden
        // unless the 'customise' checkbox is checked.

        $mform->addElement('header', 'advancedcustomisationheader',
                get_string('advanced_customisation','qtype_coderunner'));

        $prototypeControls = array();

        $prototypeSelect =& $mform->createElement('select', 'prototype_type',
                get_string('prototypeQ', 'qtype_coderunner'));
        $prototypeSelect->addOption('No', '0');
        $prototypeSelect->addOption('Yes (built-in)', '1', array('disabled'=>'disabled'));
        $prototypeSelect->addOption('Yes (user defined)', '2');
        $prototypeControls[] =& $prototypeSelect;
        $prototypeControls[] =& $mform->createElement('text', 'type_name',
                get_string('question_type_name', 'qtype_coderunner'), array('size' => 30));
        $mform->addElement('group', 'prototypecontrols',
                get_string('prototypecontrols', 'qtype_coderunner'),
                $prototypeControls, NULL, false);
        $mform->setDefault('is_prototype', False);
        $mform->setType('type_name', PARAM_RAW);
        $mform->addElement('hidden', 'saved_prototype_type');
        $mform->setType('saved_prototype_type', PARAM_RAW);
        $mform->addHelpButton('prototypecontrols', 'prototypecontrols', 'qtype_coderunner');


        $sandboxControls = array();

        $sandboxes = array('DEFAULT' => 'DEFAULT');
        foreach ($SANDBOXES as $box) {
            $sandboxes[$box] = $box;
        }

        $sandboxControls[] =  $mform->createElement('select', 'sandbox', NULL, $sandboxes);

        $sandboxControls[] =& $mform->createElement('text', 'cputimelimitsecs',
                get_string('cputime', 'qtype_coderunner'), array('size' => 5));
        $sandboxControls[] =& $mform->createElement('text', 'memlimitmb',
                get_string('memorylimit', 'qtype_coderunner'), array('size' => 5));
        $mform->addElement('group', 'sandboxcontrols',
                get_string('sandboxcontrols', 'qtype_coderunner'),
                $sandboxControls, NULL, false);
        $mform->setType('cputimelimitsecs', PARAM_RAW);
        $mform->setType('memlimitmb', PARAM_RAW);
        $mform->addHelpButton('sandboxcontrols', 'sandboxcontrols', 'qtype_coderunner');

        $mform->addElement('text', 'language', get_string('language', 'qtype_coderunner'),
                array('size' => 10));
        $mform->setType('language', PARAM_RAW);
        $mform->addHelpButton('language', 'language', 'qtype_coderunner');

        $combinatorControls = array();

        $combinatorControls[] =& $mform->createElement('advcheckbox', 'enable_combinator', NULL,
                get_string('enablecombinator', 'qtype_coderunner'));
        $combinatorControls[] =& $mform->createElement('text', 'test_splitter_re',
                get_string('test_splitter_re', 'qtype_coderunner'),
                array('size' => 45));
        $mform->setType('test_splitter_re', PARAM_RAW);
        $mform->disabledIf('type_name', 'prototype_type', 'neq', '2');

        $combinatorControls[] =& $mform->createElement('textarea', 'combinator_template',
                '',
                array('rows'=>8, 'cols'=>80, 'class'=>'template edit_code',
                       'name'=>'combinator_template'));

        $mform->addElement('group', 'combinator_controls',
                get_string('combinator_controls', 'qtype_coderunner'),
                $combinatorControls, NULL, false);

        $mform->addHelpButton('combinator_controls', 'combinator_controls', 'qtype_coderunner');

        $mform->setExpanded('customisationheader');  // Although expanded it's hidden until JavaScript unhides it

        $PAGE->requires->js_init_call('M.qtype_coderunner.setupAllTAs',  array(), false, $jsmodule);
        $PAGE->requires->js_init_call('M.qtype_coderunner.initEditForm', array(), false, $jsmodule);
        parent::definition($mform);
    }


    public function definition_inner($mform) {

        // TODO: what was the purpose of the next 2 lines?
        //$mform->addElement('static', 'answersinstruct');
        //$mform->closeHeaderBefore('answersinstruct');

        if (isset($this->question->options->testcases)) {
            $numTestcases = count($this->question->options->testcases) + NUM_TESTCASES_ADD;
        }
        else {
            $numTestcases = NUM_TESTCASES_START;
        }

        // Confusion alert! A call to $mform->setDefault("mark[$i]", '1.0') looks
        // plausible and works to set the empty-form default, but it then
        // overrides (rather than is overridden by) the actual value. The same
        // thing happens with $repeatedoptions['mark']['default'] = 1.000 in
        // get_per_testcase_fields (q.v.).
        // I don't understand this (but see 'Evil hack alert' in the baseclass).
        // MY EVIL HACK ALERT -- setting just $numTestcases default values
        // fails when more test cases are added on the fly. So I've set up
        // enough defaults to handle 5 successive adding of more test cases.
        // I believe this is a bug in the underlying Moodle question type, not
        // mine, but ... how to be sure?
        $mform->setDefault('mark', array_fill(0, $numTestcases + 5 * NUM_TESTCASES_ADD, 1.0));

        $this->add_per_testcase_fields($mform, get_string('testcase', 'qtype_coderunner', "{no}"),
                $numTestcases);

        // Add the option to attach runtime support files, all of which are
        // copied into the working directory when the expanded template is
        // executed.The file context is that of the current course.
        $options = $this->fileoptions;
        $options['subdirs'] = false;
        $mform->addElement('header', 'fileheader',
                get_string('fileheader', 'qtype_coderunner'));
        $mform->addElement('filemanager', 'datafiles',
                get_string('datafiles', 'qtype_coderunner'), null,
                $options);
        $mform->addHelpButton('datafiles', 'datafiles', 'qtype_coderunner');

        // Lastly add the standard moodle question stuff
        $this->add_interactive_settings();
    }


 /**
     * Add a set of form fields, obtained from get_per_test_fields, to the form,
     * one for each existing testcase, with some blanks for some new ones
     * This overrides the base-case version because we're dealing with test
     * cases, not answers.
     * @param object $mform the form being built.
     * @param $label the label to use for each option.
     * @param $gradeoptions the possible grades for each answer.
     * @param $minoptions the minimum number of testcase blanks to display.
     *      Default QUESTION_NUMANS_START.
     * @param $addoptions the number of testcase blanks to add. Default QUESTION_NUMANS_ADD.
     */
    protected function add_per_testcase_fields(&$mform, $label, $numTestcases) {
        $mform->addElement('header', 'answerhdr',
                    get_string('testcases', 'qtype_coderunner'), '');
        $mform->setExpanded('answerhdr', 1);
        $repeatedoptions = array();
        $repeated = $this->get_per_testcase_fields($mform, $label, $repeatedoptions);
        $this->repeat_elements($repeated, $numTestcases, $repeatedoptions,
                'numtestcases', 'addanswers', QUESTION_NUMANS_ADD,
                $this->get_more_choices_string(), true);
        $n = $numTestcases + QUESTION_NUMANS_ADD;
        for ($i = 0; $i < $n; $i++) {
            $mform->disabledIf("mark[$i]", 'all_or_nothing', 'checked');
        }
    }


    /*
     *  A rewritten version of get_per_answer_fields specific to test cases.
     */
    public function get_per_testcase_fields($mform, $label, &$repeatedoptions) {
        $repeated = array();
        $repeated[] = & $mform->createElement('textarea', 'testcode',
                $label,
                array('cols' => 80, 'rows' => 3, 'class' => 'testcaseexpression edit_code'));
        $repeated[] = & $mform->createElement('textarea', 'stdin',
                get_string('stdin', 'qtype_coderunner'),
                array('cols' => 80, 'rows' => 3, 'class' => 'testcasestdin edit_code'));
        $repeated[] = & $mform->createElement('textarea', 'expected',
                get_string('expected', 'qtype_coderunner'),
                array('cols' => 80, 'rows' => 3, 'class' => 'testcaseresult edit_code'));

        $group[] =& $mform->createElement('checkbox', 'useasexample', NULL,
                get_string('useasexample', 'qtype_coderunner'));

        $options = array();
        foreach ($this->displayOptions() as $opt) {
            $options[$opt] = get_string($opt, 'qtype_coderunner');
        }

        $group[] =& $mform->createElement('select', 'display',
                        get_string('display', 'qtype_coderunner'), $options);
        $group[] =& $mform->createElement('checkbox', 'hiderestiffail', NULL,
                        get_string('hiderestiffail', 'qtype_coderunner'));
        $group[] =& $mform->createElement('text', 'mark',
                get_string('mark', 'qtype_coderunner'), array('size' => 5));

        $repeated[] =& $mform->createElement('group', 'testcasecontrols',
                        get_string('row_properties', 'qtype_coderunner'),
                        $group, NULL, false);

        $repeatedoptions['expected']['type'] = PARAM_RAW;
        $repeatedoptions['testcode']['type'] = PARAM_RAW;
        $repeatedoptions['stdin']['type'] = PARAM_RAW;
        $repeatedoptions['mark']['type'] = PARAM_FLOAT;
        // $repeatedoptions['mark']['default'] = 1.000;  TODO: Why does this break? Moodle bug??

        return $repeated;
    }


    // A list of the allowed values of the DB 'display' field for each testcase.
    protected function displayOptions() {
        return array('SHOW', 'HIDE', 'HIDE_IF_FAIL', 'HIDE_IF_SUCCEED');
    }


    public function data_preprocessing($question) {
        // Load question data into form ($this). Called by set_data after
        // standard stuff all loaded.
        global $COURSE;
        if (isset($question->options->testcases)) { // Reloading a saved question?
            $question->testcode = array();
            $question->expected = array();
            $question->useasexample = array();
            $question->display = array();
            $question->hiderestifail = array();
            foreach ($question->options->testcases as $tc) {
                $question->testcode[] = $this->newlineHack($tc->testcode);
                $question->stdin[] = $this->newlineHack($tc->stdin);
                $question->expected[] = $this->newlineHack($tc->expected);
                $question->useasexample[] = $tc->useasexample;
                $question->display[] = $tc->display;
                $question->hiderestiffail[] = $tc->hiderestiffail;
                $question->mark[] = sprintf("%.3f", $tc->mark);
            }

            // The customise field isn't listed as an extra-question-field so also
            // needs to be copied down from the options here.
            $question->customise = $question->options->customise;

            // Save the prototype_type so can see if it changed on post-back
            $question->saved_prototype_type = $question->prototype_type;
            $question->courseid = $COURSE->id;

            // Load the type-name if this is a prototype, else make it blank
            if ($question->prototype_type != 0) {
                $question->type_name = $question->coderunner_type;
            } else {
                $question->type_name = '';
            }
        }

        $draftid = file_get_submitted_draft_itemid('datafiles');
        $options = $this->fileoptions;
        $options['subdirs'] = false;

        file_prepare_draft_area($draftid, $this->context->id,
                'qtype_coderunner', 'datafile',
                empty($question->id) ? null : (int) $question->id,
                $options);
        $question->datafiles = $draftid; // File manager needs this (and we need it when saving)
        return $question;
    }
    
    
    // A horrible horrible hack for a horrible horrible browser "feature".
    // Inserts a newline at the start of a text string that's going to be
    // displayed at the start of a <textarea> element, because all browsers
    // strip a leading newline. If there's one there, we need to keep it, so
    // the extra one ensures we do. If there isn't one there, this one gets
    // ignored anyway.
    private function newlineHack($s) {
        return "\n" . $s;
    }


    
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['coderunner_type'] == 'Undefined') {
            $errors['coderunner_type_group'] = get_string('questiontype_required', 'qtype_coderunner');
        }
        if ($data['cputimelimitsecs'] != '' &&
             (!ctype_digit($data['cputimelimitsecs']) || intval($data['cputimelimitsecs']) <= 0)) {
            $errors['sandboxcontrols'] = get_string('badcputime', 'qtype_coderunner');
        }
        if ($data['memlimitmb'] != '' &&
             (!ctype_digit($data['memlimitmb']) || intval($data['memlimitmb']) < 0)) {
            $errors['sandboxcontrols'] = get_string('badmemlimit', 'qtype_coderunner');
        }

        if ($data['prototype_type'] == 0) {
            // Unless it's a prototype it needs at least one testcase
            $testCaseErrors = $this->validate_test_cases($data);
            $errors = array_merge($errors, $testCaseErrors);
        }


        if ($data['prototype_type'] == 2 && ($data['saved_prototype_type'] != 2 ||
                   $data['type_name'] != $data['coderunner_type'])){
            // User-defined prototype, either newly created or undergoing a name change
            $typeName = trim($data['type_name']);
            if ($typeName === '') {
                $errors['prototypecontrols'] = get_string('empty_new_prototype_name', 'qtype_coderunner');
            } else if (!$this->is_valid_new_type($typeName)) {
                $errors['prototypecontrols'] = get_string('bad_new_prototype_name', 'qtype_coderunner');
            }
        }

        if (trim($data['penalty_regime']) != '') {
            $bits = explode(',', $data['penalty_regime']);
            $n = count($bits);
            for ($i = 0; $i < $n; $i++) {
                $bit = trim($bits[$i]);
                if ($bit === '...') {
                    if ($i != $n - 1 || $n < 3 || floatval($bits[$i - 1]) <= floatval($bits[$i - 2])) {
                        $errors['penalty_regime'] = get_string('bad_dotdotdot', 'qtype_coderunner');
                    }
                }
            }
        }

        return $errors;
    }

    // True iff the given name is valid for a new type, i.e., it's not in use
    // in the current context (Currently only a single global context is
    // implemented).
    private function is_valid_new_type($typeName) {
        list($langs, $types) = $this->get_languages_and_types();
        return !array_key_exists($typeName, $types);
    }


    private function get_languages_and_types() {
        // Return two arrays (language => language_upper_case) and (type => subtype) of
        // all the coderunner question types available in the current course
        // context.
        // The subtype is the suffix of the type in the database,
        // e.g. for java_method it is 'method'. The language is the bit before
        // the underscore, and language_upper_case is a capitalised version,
        // e.g. Java for java. For question types without a
        // subtype the word 'Default' is used.

        $records = qtype_coderunner::getAllPrototypes();
        $types = array();
        foreach ($records as $row) {
            if (($pos = strpos($row->coderunner_type, '_')) !== FALSE) {
                $subtype = substr($row->coderunner_type, $pos + 1);
                $language = substr($row->coderunner_type, 0, $pos);
            }
            else {
                $subtype = 'Default';
                $language = $row->coderunner_type;
            }
            $types[$row->coderunner_type] = $row->coderunner_type;
            $languages[$language] = ucwords($language);
        }
        asort($types);
        asort($languages);
        return array($languages, $types);
    }



    // Validate the test cases
    private function validate_test_cases($data) {
        $errors = array(); // Return value
        $testcodes = $data['testcode'];
        $stdins = $data['stdin'];
        $expecteds = $data['expected'];
        $marks = $data['mark'];
        $count = 0;
        $cntNonemptyTests = 0;
        $num = max(count($testcodes), count($stdins), count($expecteds));
        for ($i = 0; $i < $num; $i++) {
            $testcode = trim($testcodes[$i]);
            if ($testcode != '') {
                $cntNonemptyTests++;
            }
            $stdin = trim($stdins[$i]);
            $expected = trim($expecteds[$i]);
            if ($testcode !== '' || $stdin != '' || $expected !== '') {
                $count++;
                $mark = trim($marks[$i]);
                if ($mark != '') {
                    if (!is_numeric($mark)) {
                        $errors["testcode[$i]"] = get_string('nonnumericmark', 'qtype_coderunner');
                    }
                    else if (floatval($mark) <= 0) {
                        $errors["testcode[$i]"] = get_string('negativeorzeromark', 'qtype_coderunner');
                    }
                }
            }
        }

        if ($count == 0) {
            $errors["testcode[0]"] = get_string('atleastonetest', 'qtype_coderunner');
        }
        else if ($cntNonemptyTests != 0 && $cntNonemptyTests != $count) {
            $errors["testcode[0]"] = get_string('allornothing', 'qtype_coderunner');
        }
        return $errors;
    }


    // Construct the HTML for a YUI3 hierarchical menu of languages/types.
    // Initially hidden and turned on by JavaScript. See module.js.
    private function makeYuiMenu($languages, $types) {
        $s = '<div id="question_types" class="yui3-menu" style="display:none"><div class="yui3-menu-content"><ul>';
        $s .= '<li class="yui3-menuitem"><a class="yui3-menu-label" href="#question-types">Choose type...</a>';
        $s .= '<div id="languages" class="yui3-menu"><div class="yui3-menu-content"><ul>';
        foreach ($languages as $lang) {
            $subtypes = array();
            foreach ($types as $type) {
                if (strpos($type, $lang) === 0) {
                    if ($type != $lang) {
                        $subtypes[$type] = substr($type, strlen($lang) + 1);
                    }
                }
            }

            $s .= '<li class="yui3-menuitem">';
            if (count($subtypes) == 0) {
                $s .= "<a class=\"yui3-menuitem-content\" href=\"#$lang\">$lang</a>";
            } else {
                $s .= '<a class="yui3-menu-label" href="#' . $lang . '">' . $lang . '</a>';
                $s .= "<div id=\"$lang\" class=\"yui3-menu\"><div class=\"yui3-menu-content\"><ul>";
                foreach ($subtypes as $type=>$subtype) {
                    $s .= "<li class=\"yui3-menuitem\"><a class=\"yui3-menuitem-content\" href=\"#$type\">$subtype</a></li>";
                }
                $s .= '</ul></div></div>';
            }
            $s .= '</li>';
        }
        $s .= '</ul></div></div>';
        $s .= '</ul></div></div>';
        return $s;

    }

}
