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

/**
 * Privacy Subsystem implementation for mod_capquiz.
 *
 * @package     mod_capquiz
 * @author      André Storhaug <andr3.storhaug@gmail.com>
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_capquiz\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\context;
use core_privacy\local\request\contextlist;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for mod_capquiz.
 *
 * @author      André Storhaug <andr3.storhaug@gmail.com>
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     * @param   collection $items The initialised collection to add metadata to.
     * @return  collection  A listing of user data stored through this system.
     */
    public static function get_metadata(collection $items): collection {
        // The table 'capquiz' stores a record for each capquiz.
        // It does not contain user personal data, but data is returned from it for contextual requirements.

        // The table 'capquiz_attempt' stores a record of each capquiz attempt.
        // It contains a userid which links to the user making the attempt and contains information about that attempt.
        $items->add_database_table('capquiz_attempt', [
            'userid' => 'privacy:metadata:capquiz_attempt:userid',
            'time_answered' => 'privacy:metadata:capquiz_attempt:time_answered',
            'time_reviewed' => 'privacy:metadata:capquiz_attempt:time_reviewed',
        ], 'privacy:metadata:capquiz_attempt');

        // The 'capquiz_question' table is used to map the usage of a question used in a CAPQuiz activity.
        // It does not contain user data.

        // The 'capquiz_question_list' table is used to store the set of question lists used by a CapQuiz activity.
        // It does not contain user data.

        // The 'capquiz_question_selection' contains selections / settings for each CAPQuiz activity.
        // It does not contain user data.

        // The 'capquiz_rating_system' does not contain any user identifying data and does not need a mapping.

        // The table 'capquiz_user' stores a record of each user in each capquiz attempt.
        // This is to kep track of rating and achievement level.
        // It contains a userid which links to the user and contains information about that user.
        $items->add_database_table('capquiz_user', [
            'userid' => 'privacy:metadata:capquiz_user:userid',
            'rating' => 'privacy:metadata:capquiz_user:rating',
            'highest_level' => 'privacy:metadata:capquiz_user:highest_level',
        ], 'privacy:metadata:capquiz_user');

        // CAPQuiz links to the 'core_question' subsystem for all question functionality.
        $items->add_subsystem_link('core_question', [], 'privacy:metadata:core_question');

        return $items;
    }

    /**
     * Get the list of contexts where the specified user has attempted a capquiz.
     *
     * @param   int $userid The user to search.
     * @return  contextlist  $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        // Select the context of any quiz attempt where a user has an attempt, plus the related usages.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {capquiz} cq ON cq.id = cm.instance
             LEFT JOIN {capquiz_attempt} ca ON ca.userid = :capquizuserid
             LEFT JOIN {capquiz_user} u ON u.capquiz_id = cq.id AND u.userid = :capquizuserid
             WHERE (
                ca.userid = :capquizuserid OR
                u.id IS NOT NULL
            )";

        $params = array_merge(
            [
                'contextlevel'      => CONTEXT_MODULE,
                'modname'           => 'capquiz',
                'capquizuserid'     => $userid,
            ],
            $qubaid->from_where_params()
        );

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    cq.*,
                    ca.id AS hasgrade,
                    ca.time_answered AS attempt_timeanswered,
                    ca.time_reviewed AS attempt_timereviewed,
                    u.id AS hasoverride,
                    u.rating AS user_rating,
                    u.highest_level AS highest_level,
                    c.id AS contextid,
                    cm.id AS cmid
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {capquiz} cq ON cq.id = cm.instance
             LEFT JOIN {capquiz_attempt} ca ON ca.userid = :capquizuserid
             LEFT JOIN {capquiz_user} u ON u.capquiz_id = cq.id AND u.userid = :capquizuserid
                 WHERE c.id {$contextsql}";

        $params = [
            'contextlevel'      => CONTEXT_MODULE,
            'modname'           => 'capquiz',
            'capquizuserid'     => $userid,
        ];
        $params += $contextparams;


        // Fetch the individual quizzes.
        $quizzes = $DB->get_recordset_sql($sql, $params);
        foreach ($quizzes as $quiz) {
            list($course, $cm) = get_course_and_cm_from_cmid($quiz->cmid, 'capquiz');
            // TODO: actually export the data
        }

    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // TODO: Implement delete_data_for_all_users_in_context() method.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // TODO: Implement delete_data_for_user() method.
    }
}
