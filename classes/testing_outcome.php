<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/** Defines a testing_outcome class which contains the complete set of
 *  results from running all the tests on a particular submission.
 *
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2013, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_coderunner\constants;

class qtype_coderunner_testing_outcome {
    const STATUS_VALID = 1;         // A full set of test results is returned.
    const STATUS_SYNTAX_ERROR = 2;  // The code (on any one test) didn't compile.
    const STATUS_BAD_COMBINATOR = 3; // A combinator template yielded an invalid result.
    const STATUS_SANDBOX_ERROR = 4;  // The run failed altogether.
    const STATUS_MISSING_PROTOTYPE = 5;  // Can't even start - no prototype.
    const STATUS_UNSERIALIZE_FAILED = 6; // A serialised outcome can't be deserialised.

    const TOLERANCE = 0.00001;       // Allowable difference between actual and max marks for a correct outcome.

    /**
     * @var int One of the STATUS_ constants above.
     * If this is not 1, subsequent fields may not be meaningful.
     */
    public $status;

    /** @var bool True if this was a precheck run. */
    public $isprecheck;

    /** @var int The number of failing test cases. */
    public $errorcount;

    /** @var string The error message to display if there are errors. */
    public $errormessage;

    /** @var int The maximum possible mark. */
    public $maxpossmark;

    /** @var int Actual mark (meaningful only if this is not an all-or-nothing question). */
    public $actualmark;

    /** @var array An array of TestResult objects. */
    public $testresults;

    /** @var ?array Array of all test runs. */
    public $sourcecodelist;

    /** @var array An associative array of sandbox info, e.g. Jobe server name. */
    public $sandboxinfo;

    /** @var int The number of failed tests. */
    public $numerrors;

    /** @var int|void Number of test results expected. */
    public $numtestsexpected;

    /** @var string For use by combinator template graders, allowing to customise grade and feedback. */
    public $graderstate;

    /** @var html_table Table for reporting validation errors. */
    public $failures;

    public function __construct($maxpossmark, $numtestsexpected, $isprecheck) {
        $this->status = self::STATUS_VALID;
        $this->isprecheck = $isprecheck;
        $this->errormessage = '';
        $this->errorcount = 0;
        $this->actualmark = 0;
        $this->maxpossmark = $maxpossmark;
        $this->numtestsexpected = $numtestsexpected;
        $this->testresults = [];
        $this->sourcecodelist = null;     // Array of all test runs on the sandbox.
        $this->sandboxinfo = [];
        $this->graderstate = '';  // For passing state between runs using combinator grader.
        $this->numerrors = 0;
        $this->failuresdata = [];      // Info for an HTML table to present ...
        $this->failuresrowclasses = []; // ... failures during validation.
    }


    /**
     * Construct a testing outcome (or combinator grader testing outcome) from
     * serialised JSON.
     * @return qtype_coderunner_testing_outcome The unserialised testing outcome (which may
     * be of the subtype class qtype_coderunner_combinator_grader_outcome) or null if the
     * unserialisation fails.
     */
    public static function unserialise_from_json($json) {
        $decodedoutcome = json_decode($json, true);
        if (is_null($decodedoutcome)) {
            return null;
        }
        // Check if we should return the combinator_grader_outcome instead. Check two expected fields in case of changes.
        if (array_key_exists('epiloguehtml', $decodedoutcome) || array_key_exists('outputonly', $decodedoutcome)) {
            $outcome = new qtype_coderunner_combinator_grader_outcome(false);
        } else {
            $outcome = new qtype_coderunner_testing_outcome(0, 0, false);
        }
        $outcome->load_attributes($decodedoutcome);
        return $outcome;
    }

    /**
     * Define the attributes of this object from the given associative array that results
     * from decoding the JSON-encoded version.
     */
    public function load_attributes($decodedoutcome) {
        foreach ($decodedoutcome as $key => $value) {
            if ($key === 'testresults') {
                foreach ($decodedoutcome['testresults'] as $tr) {
                    $row = new qtype_coderunner_test_result(new StdClass(), false, 0, '');
                    foreach ($tr as $key => $value) {
                        $row->$key = $value;
                    }
                    $this->testresults[] = $row;
                }
            } else {
                $this->$key = $value;
            }
        }
    }


    public function set_status($status, $errormessage = '') {
        $this->status = $status;
        $this->errormessage = $errormessage;
    }

    public function iscombinatorgrader() {
        return false;
    }

    /**
     * Return True iff this is outcome is from a precheck run else false.
     * The optional parameter $qa, the current question attempt from which
     * this outcome was extracted, is for legacy support and should be deleted
     * in 2018. It is present to allow rendering of stored outcomes from
     * earlier versions of CodeRunner that did not have precheck stored in
     * the outcome.
     * @return type
     */
    public function is_precheck(question_attempt $qa = null) {
        if (isset($this->isprecheck)) {
            // Always true if outcome generated by this version of CodeRunner.
            return $this->isprecheck;
        } else if ($qa != null) {
            // Rendering a legacy outcome. Render using the logic from the
            // flawed legacy version.
            return $qa->get_last_behaviour_var('_precheck', 0);
        } else {
            throw new coding_exception("Bad call to outcome.is_precheck()");
        }
    }

    public function run_failed() {
        return ($this->status === self::STATUS_SANDBOX_ERROR) ||
               ($this->status === self::STATUS_MISSING_PROTOTYPE);
    }

    public function invalid() {
        return $this->status === self::STATUS_UNSERIALIZE_FAILED;
    }

    public function has_syntax_error() {
        return $this->status === self::STATUS_SYNTAX_ERROR;
    }

    public function combinator_error() {
        return $this->status === self::STATUS_BAD_COMBINATOR;
    }

    public function is_ungradable() {
        return $this->run_failed() || $this->combinator_error();
    }

    public function is_output_only() {
        return false;
    }

    public function mark_as_fraction() {
        if ($this->status === self::STATUS_VALID) {
            // Need to return exactly 1.0 for a right answer.
            $fraction = $this->actualmark / $this->maxpossmark;
            return abs($fraction - 1.0) < self::TOLERANCE ? 1.0 : $fraction;
        } else {
            return 0;
        }
    }

    public function all_correct() {
        return $this->mark_as_fraction() === 1.0;
    }

    // True if the number of tests does not equal the number originally
    // expected, meaning that testing was aborted.
    public function was_aborted() {
        return count($this->testresults) != $this->numtestsexpected;
    }


    public function add_test_result($tr) {
        $this->testresults[] = $tr;
        $this->actualmark += $tr->awarded;
        if (!$tr->iscorrect) {
            $this->errorcount++;
        }
    }

    /**
     *
     * @param associative array $info
     * Merge the given sandbox associative array with $this->sandboxinfo
     */
    public function add_sandbox_info($info) {
        $this->sandboxinfo = array_merge($this->sandboxinfo, $info);
    }


    /**
     * Add to the $this->failures table a report on a failed testcase, including
     * a button to copy the got back into the expected
     * @param type $rownum
     * @param type $code
     * @param type $expected
     * @param type $got
     */
    protected function add_failed_test($rownum, $code, $expected, $got, $sanitise = true) {
        $this->failuresdata[] = $this->format_failed_test($rownum, $code, $expected, $got, $sanitise);
        $this->failuresrowclasses[] = 'coderunner-failed-test failrow_' . $rownum;
    }

    /**
     * Return an array of 3-elements for placing in a row of a table containing
     * all the failed tests during validation. The first element is the failed
     * test (including a link to it), the second is the expected result (including
     * a link to it) and the third is what we actually got, together with a
     * button that, if clicked, copies the got back into the test case's
     * expected field.
     * @param int $rownum The row number of the test that failed
     * @param string $code The test code that failed.
     * @param string $expected The expected result.
     * @param string $got The actual output from the test.
     * @param bool $sanitise True to apply the usual htmlspecialcharacter translations
     * on expected and got. This translation is always done on code regardless of
     * this parameter setting.Sanitising should be turned off when formatting
     * columns with an 'h' column specifier.
     * @return array The three HTML strings to be inserted into the pseudo result table.
     */
    protected function format_failed_test($rownum, $code, $expected, $got, $sanitise = true) {
        $nl = html_writer::empty_tag('br');
        if ($sanitise) {
            $expected = s($expected);
            $got = s($got);
        }
        $testcode = html_writer::link(
            '#id_testcode_' . $rownum,
            get_string('testcase', 'qtype_coderunner', $rownum + 1)
        ) . "$nl<pre>$code</pre>";
        $expected = html_writer::link(
            '#id_expected_' . $rownum,
            html_writer::tag(
                'pre',
                $expected,
                ['id' => 'id_fail_expected_' . $rownum]
            )
        );
        $gotpre = html_writer::tag('pre', $got, ['id' => 'id_got_' . $rownum]);
        $button = html_writer::tag('button', '&lt;&lt;', [
                                   'type' => 'button', // To suppress form submission.
                                   'class' => 'replaceexpectedwithgot']);
        return [$testcode, $expected, $gotpre . $button];
    }

    // Return a message summarising the nature of the error if this outcome
    // is not all correct.
    public function validation_error_message() {
        if ($this->invalid()) {
            return html_writer::tag('pre', $this->errormessage);
        } else if ($this->run_failed()) {
            return get_string('run_failed', 'qtype_coderunner');
        } else if ($this->has_syntax_error()) {
            return get_string('syntax_errors', 'qtype_coderunner') . html_writer::tag('pre', $this->errormessage);
        } else if ($this->combinator_error()) {
            return get_string('badquestion', 'qtype_coderunner') . html_writer::tag('pre', $this->errormessage);
        } else if (!$this->iscombinatorgrader()) {  // See combinator_grader_outcome for this more complex case.
            foreach ($this->testresults as $i => $testresult) {
                if (!$testresult->iscorrect) {
                    $this->numerrors += 1;
                    $rownum = isset($testresult->rownum) ? intval($testresult->rownum) : $i;
                    if (isset($testresult->expected) && isset($testresult->got)) {
                        $code = $testresult->testcode;
                        $expected = $testresult->expected;
                        $got = $testresult->got;
                        $this->add_failed_test($rownum, $code, $expected, $got);
                    }
                }
            }
            $message = get_string('failedntests', 'qtype_coderunner', [
                'numerrors' => $this->numerrors]);
            if ($this->failuresdata) {
                $htmltable = new html_table();
                $htmltable->attributes['class'] = 'coderunner-test-results';
                $htmltable->head = [
                    get_string('testcolhdr', 'qtype_coderunner'),
                    get_string('expectedcolhdr', 'qtype_coderunner'),
                    get_string('gotcolhdr', 'qtype_coderunner'),
                ];
                $htmltable->data = $this->failuresdata;
                $htmltable->rowclasses = $this->failuresrowclasses;
                $message .= html_writer::table($htmltable) . get_string('replaceexpectedwithgot', 'qtype_coderunner');
            }
        } else {
            $message = get_string('failedtesting', 'qtype_coderunner');
        }
        return $message . html_writer::empty_tag('br') . get_string('howtogetmore', 'qtype_coderunner');
    }

    /**
     *
     * @global type $COURSE
     * @param qtype_coderunner $question
     * @return a table of test results.
     * The test result table is an array of table rows (each an array).
     * The first row is a header row, containing strings like 'Test', 'Expected',
     * 'Got' etc. Other rows are the values of those items for the different
     * tests that were run.
     * There are two special case columns. If the header is 'iscorrect', the
     * value in the row should be 0 or 1. The header of this column is left blank
     * and the row contents are replaced by a tick or a cross. There can be
     * multiple iscorrect columns. If the header is
     * 'ishidden', the column is not displayed but instead the row itself is
     * hidden from view unless the user has the grade:viewhidden capability.
     *
     * The set of columns to be displayed is specified by the question's
     * resultcolumns variable (which should be accessed via its result_columns
     * method). The resultcolumns attribute is a JSON-encoded list of column specifiers.
     * A column specifier is itself a list, usually with 2 or 3 elements.
     * The first element is the column header the second is (usually) the test
     * result object field name whose value is to be displayed in the column
     * and the third (optional) element is the sprintf format used to display
     * the field. It is also possible to combine more than one field of the
     * test result object into a single field by adding extra field names into
     * the column specifier before the format, which is then mandatory.
     * For example, to display the mark awarded for a test case as, say
     * '0.71 out of 1.00' the column specifier would be
     * ["Mark", "awarded", "mark", "%.2f out of %.2f"] A special case format
     * specifier is '%h' denoting that the result object field value should be
     * treated as ready-to-output html. Empty columns are suppressed.
     */
    protected function build_results_table(qtype_coderunner_question $question) {
        $resultcolumns = $question->result_columns();
        $canviewhidden = self::can_view_hidden();

        // Build the table header, containing all the specified field headers,
        // unless all rows in that column would be blank.

        $columnheaders = ['iscorrect']; // First column is a tick or cross, like last column.
        $hiddencolumns = [];  // Array of true/false for each element of $colspec.
        $numvisiblecolumns = 0;

        foreach ($resultcolumns as $colspec) {
            $len = count($colspec);
            if ($len < 3) {
                $colspec[] = '%s';  // Add missing default format.
            }
            $header = $colspec[0];
            $field = $colspec[1];  // Primary field - there may be more.
            $numnonblank = self::count_non_blanks($field, $this->testresults);
            if ($numnonblank == 0) {
                $hiddencolumns[] = true;
            } else {
                $columnheaders[] = $header;
                $hiddencolumns[] = false;
                $numvisiblecolumns += 1;
            }
        }
        if ($numvisiblecolumns > 1) {
            $columnheaders[] = 'iscorrect';  // Tick or cross at the end, unless <= 1 visible columns.
        }
        $columnheaders[] = 'ishidden';   // Last column controls if row hidden or not.

        $table = [$columnheaders];

        // Process each row of the results table.
        $hidingrest = false;
        foreach ($this->testresults as $testresult) {
            $testisvisible = $this->should_display_result($testresult) && !$hidingrest;
            if ($canviewhidden || $testisvisible) {
                $fraction = $testresult->awarded / $testresult->mark;
                $tablerow = [$fraction];   // Will be rendered as tick or cross.
                $icol = 0;
                foreach ($resultcolumns as $colspec) {
                    $len = count($colspec);
                    if ($len < 3) {
                        $colspec[] = '%s';  // Add missing default format.
                    }
                    if (!$hiddencolumns[$icol]) {
                        $len = count($colspec);
                        $format = $colspec[$len - 1];
                        if ($format === '%h') {  // If it's an html format, use value wrapped in an HTML wrapper.
                            $value = $testresult->gettrimmedvalue($colspec[1]);
                            $tablerow[] = new qtype_coderunner_html_wrapper($value);
                        } else if ($format !== '') {  // Else if it's a non-null column.
                            $args = [$format];
                            for ($j = 1; $j < $len - 1; $j++) {
                                $value = $testresult->gettrimmedvalue($colspec[$j]);
                                $args[] = $value;
                            }
                            $content = call_user_func_array('sprintf', $args);
                            $tablerow[] = $content;
                        }
                    }
                    $icol += 1;
                }
                if ($numvisiblecolumns > 1) { // Suppress trailing tick or cross in degenerate case.
                    $tablerow[] = $fraction;
                }
                $tablerow[] = !$testisvisible;
                $table[] = $tablerow;
            }

            if ($testresult->hiderestiffail && !$testresult->iscorrect) {
                $hidingrest = true;
            }
        }

        return $table;
    }


    // Count the number of errors in hidden testcases, given the array of
    // testresults.
    public function count_hidden_errors() {
        $count = 0;
        $hidingrest = false;
        foreach ($this->testresults as $tr) {
            if ($hidingrest) {
                $isdisplayed = false;
            } else {
                $isdisplayed = $this->should_display_result($tr);
            }
            if (!$isdisplayed && !$tr->iscorrect) {
                $count++;
            }
            if ($tr->hiderestiffail && !$tr->iscorrect) {
                $hidingrest = true;
            }
        }
        return $count;
    }


    // True iff the given test result should be displayed.
    protected static function should_display_result($testresult) {
        return !isset($testresult->display) ||  // E.g. broken combinator template?
             $testresult->display == 'SHOW' ||
            ($testresult->display == 'HIDE_IF_FAIL' && $testresult->iscorrect) ||
            ($testresult->display == 'HIDE_IF_SUCCEED' && !$testresult->iscorrect);
    }


    // Support function to count how many objects in the given list of objects
    // have the given 'field' attribute non-blank. Non-existent fields are also
    // included in order to generate a column showing the error, but null values.
    protected static function count_non_blanks($field, $objects) {
        $n = 0;
        foreach ($objects as $obj) {
            if (
                !property_exists($obj, $field) ||
                (!is_null($obj->$field) && !is_string($obj->$field)) ||
                (is_string($obj->$field) && trim($obj->$field !== ''))
            ) {
                $n++;
            }
        }
        return $n;
    }


    /**
     *
     * @global type $COURSE the current course (if there is one)
     * @return boolean true iff the current user has permissions to view hidden rows
     */
    public static function can_view_hidden() {
        global $COURSE;

        if ($COURSE && $coursecontext = context_course::instance($COURSE->id)) {
            $canviewhidden = has_capability('qtype/coderunner:viewhiddentestcases', $coursecontext);
        } else {
            $canviewhidden = false;
        }

        return $canviewhidden;
    }


    // Getter methods for use by renderer.
    // ==================================.

    public function get_test_results(qtype_coderunner_question $q) {
        return $this->build_results_table($q);
    }

    // Called only in case of precheck == 1, and no errors.
    public function get_raw_output() {
        assert(count($this->testresults) === 1);
        $testresult = $this->testresults[0];
        assert(empty($testresult->stderr));
        return $testresult->got;
    }

    public function get_prologue() {
        return '';
    }

    public function get_epilogue() {
        return '';
    }

    public function get_sourcecode_list() {
        return $this->sourcecodelist;
    }

    public function get_error_count() {
        return $this->errorcount;
    }

    public function get_sandbox_info() {
        return $this->sandboxinfo;
    }
}
