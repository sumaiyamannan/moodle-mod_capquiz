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

namespace mod_capquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     mod_capquiz
 * @author      Aleksander Skrede <aleksander.l.skrede@ntnu.no>
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capquiz_question {

    /** @var \stdClass $record */
    private $record;

    public function __construct(\stdClass $record) {
        global $DB;
        $this->record = $record;
        $question = $DB->get_record(database_meta::$tablemoodlequestion, [
            database_meta::$fieldid => $record->question_id
        ]);
        if ($question !== false) {
            $this->record->name = $question->name;
            $this->record->text = $question->questiontext;
        } else {
            $this->record->name = 'Missing question';
            $this->record->text = 'This question is missing.';
        }
    }

    public static function load(int $questionid) {
        global $DB;
        $record = $DB->get_record(database_meta::$tablequestion, [database_meta::$fieldid => $questionid]);
        if ($record === false) {
            return null;
        }
        return new capquiz_question($record);
    }

    public function entry() : \stdClass {
        return $this->record;
    }

    public function id() : int {
        return $this->record->id;
    }

    public function question_id() : int {
        return $this->record->question_id;
    }

    public function question_list_id() : int {
        return $this->record->question_list_id;
    }

    public function rating() : float {
        return $this->record->rating;
    }

    public function set_rating(float $rating) : bool {
        global $DB;
        $db_entry = $this->record;
        $db_entry->rating = $rating;
        if ($DB->update_record(database_meta::$tablequestion, $db_entry)) {
            $this->record = $db_entry;
            return true;
        }
        return false;
    }

    public function name() : string {
        return $this->record->name;
    }

    public function text() : string {
        return $this->record->text;
    }

}
