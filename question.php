<?php
// This file is for Moodle - http://moodle.org/
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
/**
 *
 *
 * @package     qtype_kprime
 * @author      Juergen Zimmer jzimmer1000@gmail.com
 * @copyright   eDaktik 2014 andreas.hruska@edaktik.at
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Represents a kprime question, a all-or-nothing variant of matrix questions.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_kprime_question extends question_graded_automatically_with_countback {

    public $rows;
    public $columns;
    public $weights;
    public $scoringmethod;
    public $shuffleoptions;
    public $numberofrows;
    public $numberofcols;

    public $order = null;

    // All the methods needed for option shuffling.
    /**
     * (non-PHPdoc)
     * @see question_definition::start_attempt()
     */
    public function start_attempt(question_attempt_step $step, $variant) {
        $this->order = array_keys($this->rows);
        if ($this->shuffleoptions) {
            shuffle($this->order);
        }
        $step->set_qt_var('_order', implode(',', $this->order));
    }

    /**
     * (non-PHPdoc)
     * @see question_definition::apply_attempt_state()
     */
    public function apply_attempt_state(question_attempt_step $step) {
        $this->order = explode(',', $step->get_qt_var('_order'));
    }

    /*
    public function get_question_summary() {
        $question = $this->html_to_text($this->questiontext, $this->questiontextformat);
        $choices = array();
        foreach ($this->order as $rowid) {
            $choices[] = $this->html_to_text($this->rows[$rowid]->optiontext,
                    $this->rows[$rowid]->optiontextformat);
        }
        return $question . ': ' . implode('; ', $choices);
    }
    */

    /**
     *
     * @param question_attempt $qa
     * @return multitype:
     */
    public function get_order(question_attempt $qa) {
        $this->init_order($qa);
        return $this->order;
    }

    /**
     * Initialises the order (if it is not set yet) by decoding
     * the question attempt variable '_order'.
     *
     * @param question_attempt $qa
     */
    protected function init_order(question_attempt $qa) {
        if (is_null($this->order)) {
            $this->order = explode(',', $qa->get_step(0)->get_qt_var('_order'));
        }
    }

    /**
     * Returns the name field name for input cells in the questiondisplay.
     * The column parameter is ignored for now since we don't use multiple answers.
     *
     * @param mixed $row
     * @param mixed $col
     * @return type
     */
    public function field($key) {
        return "option" . $key;
    }

    /**
     * Checks whether an row is answered by a given response.
     *
     * @param type $response
     * @param type $row
     * @param type $col
     *
     * @return boolean
     */
    public function is_answered($response, $rownumber) {
        $field = $this->field($rownumber);
        // Get the value of the radiobutton array, if it exists in the response.
        return isset($response[$field]) && !empty($response[$field]);
    }

    /**
     * Checks whether a given column (response) is the correct answer for a given row (option).
     *
     * @param string $row The row number.
     * @param string $col The column number
     * @return boolean
     */
    public function is_correct($row, $col) {
        $weight = $this->weight($row, $col);

        if ($weight > 0.0) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Returns the weight for the given row and column.
     *
     * @param mixed $row A row object or a row number.
     * @param mixed $col A column object or a column number.
     * @return float
     */
    public function weight($row = null, $col = null) {
        $rownumber = is_object($row) ? $row->number : $row;
        $colnumber = is_object($col) ? $col->number : $col;
        $weight = (float) $this->weights[$rownumber][$colnumber]->weight;
        return $weight;
    }


    public function is_row_selected($response, $rownumber) {
        return isset($response[$this->field($rownumber)]);
    }

    public function get_response(question_attempt $qa) {
        return $qa->get_last_qt_data();
    }

    /**
     * Used by many of the behaviours, to work out whether the student's
     * response to the question is complete. That is, whether the question attempt
     * should move to the COMPLETE or INCOMPLETE state.
     *
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return bool whether this response is a complete answer to this question.
     */
    public function is_complete_response(array $response) {
        // A response is complete if a field exists in the response for every row.
        foreach ($this->order as $key => $rowid) {
            if (!isset($response[$this->field($key)])) {
                return false;
            }
        }
        return true;
    }


    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        $isgradable = $this->is_gradable_response($response);
        if ($isgradable) {
            return '';
        }
        return qtype_kprime::get_string('oneanswerperrow');
    }

    /**
     * (non-PHPdoc)
     * @see question_graded_automatically::is_gradable_response()
     */
    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    /**
     * Produce a plain text summary of a response.
     *
     * @param $response a response, as might be passed to {@link grade_response()}.
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function summarise_response(array $response) {
        $result = array();

        foreach ($this->order as $key => $rowid) {
            $field = $this->field($key);
            $row = $this->rows[$rowid];

            if (isset($response[$field])) {
                foreach ($this->columns as $column) {
                    if ($column->number == $response[$field]) {
                        $result[] = $this->html_to_text($row->optiontext, $row->optiontextformat) .
                        ': ' . $this->html_to_text($column->responsetext, $column->responsetextformat);
                    }
                }
            }
        }
        return implode("; ", $result);
    }

    /*
    public function classify_response(array $response) {
        if (!array_key_exists('answer', $response) ||
                !array_key_exists($response['answer'], $this->order)) {
            return array($this->id => question_classified_response::no_response());
        }
        $choiceid = $this->order[$response['answer']];
        $ans = $this->answers[$choiceid];
        return array($this->id => new question_classified_response($choiceid,
                $this->html_to_text($ans->answer, $ans->answerformat), $ans->fraction));
    }
    */

    /**
     * Use by many of the behaviours to determine whether the student's
     * response has changed. This is normally used to determine that a new set
     * of responses can safely be discarded.
     *
     * @param array $prevresponse the responses previously recorded for this question,
     *      as returned by {@link question_attempt_step::get_qt_data()}
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same - that is
     *      whether the new set of responses can safely be discarded.
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        if (count($prevresponse) != count($newresponse)) {
            return false;
        }
        foreach ($prevresponse as $field => $previousvalue) {
            if (!isset($newresponse[$field])) {
                return false;
            }
            $newvalue = $newresponse[$field];
            if ($newvalue != $previousvalue) {
                return false;
            }
        }

        return true;
    }

    /**
     * What data would need to be submitted to get this question correct.
     * If there is more than one correct answer, this method should just
     * return one possibility.
     *
     * @return array parameter name => value.
     */
    public function get_correct_response($rowidindex = false) {
        $result = array();
        foreach ($this->order as $key => $rowid) {
            $row = $this->rows[$rowid];
            $field = $this->field($key);

            foreach ($this->columns as $column) {
                $weight = $this->weight($row, $column);
                if ($weight > 0) {
                    if ($rowidindex) {
                        $result[$rowid] = $column->id;
                    } else {
                        $result[$field] = $column->number;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Returns an instance of the grading class according to the scoringmethod of the question.
     *
     * @return The grading object.
     */
    public function grading() {
        global $CFG;

        $type = $this->scoringmethod;
        $gradingclass = 'qtype_kprime_grading_' . $type;

        require_once($CFG->dirroot . '/question/type/kprime/grading/' . $gradingclass . '.class.php');
        return new $gradingclass();
    }


    /**
     * Grade a response to the question, returning a fraction between
     * get_min_fraction() and 1.0, and the corresponding {@link question_state}
     * right, partial or wrong.
     *
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return array (number, integer) the fraction, and the state.
     */
    public function grade_response(array $response) {
        $grade = $this->grading()->grade_question($this, $response);
        $state = question_state::graded_state_for_fraction($grade);
        return array($grade, $state);
    }

    /**
     * What data may be included in the form submission when a student submits
     * this question in its current state?
     *
     * This information is used in calls to optional_param. The parameter name
     * has {@link question_attempt::get_field_prefix()} automatically prepended.
     *
     * @return array|string variable name => PARAM_... constant, or, as a special case
     *      that should only be used in unavoidable, the constant question_attempt::USE_RAW_DATA
     *      meaning take all the raw submitted data belonging to this question.
     */
    public function get_expected_data() {
        $result = array();
        foreach ($this->order as $key => $notused) {
            $field = $this->field($key);
            $result[$field] = PARAM_INT;
        }
        return $result;
    }

    /**
     * Returns an array where keys are the cell names and the values
     * are the weights
     *
     * @return array
     */
    public function cells() {
        $result = array();
        foreach ($this->order as $key => $rowid) {
            $row = $this->rows[$rowid];
            $field = $this->field($key);
            foreach ($this->columns as $column) {
                $result[$field] = $this->weight($row->number, $column->number);
            }
        }
        return $result;
    }

    /**
     * Makes HTML text (e.g. option or feedback texts) suitable for inline presentation in renderer.php
     *
     * @param string html The HTML code.
     * @return string the purified HTML code without paragraph elements and line breaks.
     */
    public function make_html_inline($html) {
        $html = preg_replace('~\s*<p>\s*~u', '', $html);
        $html = preg_replace('~\s*</p>\s*~u', '<br />', $html);
        $html = preg_replace('~(<br\s*/?>)+$~u', '', $html);
        return trim($html);
    }

    /**
     * Convert some part of the question text to plain text. This might be used,
     * for example, by get_response_summary().
     * @param string $text The HTML to reduce to plain text.
     * @param int $format the FORMAT_... constant.
     * @return string the equivalent plain text.
     */
    public function html_to_text($text, $format) {
        return question_utils::to_plain_text($text, $format);
    }


    public function compute_final_grade($responses, $totaltries) {
        $totalstemscore = 0;
        foreach ($this->order as $key => $rowid) {
            $fieldname = $this->field($key);

            $lastwrongindex = -1;
            $finallyright = false;
            foreach ($responses as $i => $response) {
                if (!array_key_exists($fieldname, $response) || !$response[$fieldname] ||
                        $this->choiceorder[$response[$fieldname]] != $this->right[$stemid]) {
                    $lastwrongindex = $i;
                    $finallyright = false;
                } else {
                    $finallyright = true;
                }
            }

            if ($finallyright) {
                $totalstemscore += max(0, 1 - ($lastwrongindex + 1) * $this->penalty);
            }
        }

        return $totalstemscore / count($this->stemorder);
    }
}
