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

require_once($CFG->dirroot . '/mod/capquiz/classes/rating_system/capquiz_rating_system_registry.php');

require_once($CFG->dirroot . '/mod/capquiz/classes/rating_system/elo_rating/elo_rating_system.php');

defined('MOODLE_INTERNAL') || die();

class capquiz_rating_system_loader {

    private $capquiz;
    private $db_entry;
    private $registry;
    private $configuration;

    public function __construct(capquiz $capquiz) {
        $this->capquiz = $capquiz;
        $this->registry = new capquiz_rating_system_registry($capquiz);
        $this->load_configuration();
    }

    public function rating_system() {
        if ($db_entry = $this->db_entry) {
            $rating_system = $this->registry->rating_system($db_entry->rating_system);
            if ($config = $this->configuration) {
                $rating_system->configure($config);
            }
            return $rating_system;
        }
        return null;
    }

    public function configuration_form(\moodle_url $url) {
        if ($db_entry = $this->db_entry) {
            if ($config = $this->configuration) {
                return $this->registry->configuration_form($db_entry->rating_system, $config, $url);
            }
        }
        return null;
    }

    public function has_rating_system() {
        if ($db_entry = $this->db_entry) {
            return $this->rating_system() != null;
        }
        return false;
    }

    public function current_rating_system_name() {
        if ($db_entry = $this->db_entry) {
            return $db_entry->rating_system;
        }
        return "No rating system specified";
    }

    public function configure_current_rating_system(\stdClass $candidate_configuration) {
        if ($db_entry = $this->db_entry) {
            $system = $this->rating_system($db_entry->rating_system);
            $system->configure($candidate_configuration);
            if ($configuration = $system->configuration()) {
                $db_entry->configuration = $this->serialize($configuration);
            } else {
                $db_entry->configuration = '';
            }
            $this->update_configuration($db_entry);
        }
    }

    public function set_rating_system(string $rating_system) {
        $system = $this->registry->rating_system($rating_system);
        $db_entry = new \stdClass;
        $db_entry->rating_system = $rating_system;
        $db_entry->capquiz_id = $this->capquiz->id();
        if ($default_configuration = $system->default_configuration()) {
            $db_entry->configuration = $this->serialize($default_configuration);
        } else {
            $db_entry->configuration = '';
        }
        global $DB;
        if ($this->db_entry) {
            $db_entry->id = $this->db_entry->id;
            $this->update_configuration($db_entry);
        } else {
            $DB->insert_record(database_meta::$table_capquiz_rating_system, $db_entry);
            $this->set_configuration($db_entry);
        }
    }

    private function load_configuration() {
        $conditions = [
            database_meta::$field_capquiz_id => $this->capquiz->id()
        ];
        global $DB;
        if ($configuration = $DB->get_record(database_meta::$table_capquiz_rating_system, $conditions)) {
            $this->set_configuration($configuration);
        }
    }

    private function update_configuration(\stdClass $configuration) {

        global $DB;
        if ($DB->update_record(database_meta::$table_capquiz_rating_system, $configuration)) {
            $this->set_configuration($configuration);
        }
    }

    private function set_configuration(\stdClass $database_entry) {
        $this->db_entry = $database_entry;
        if ($configuration = $this->deserialize($database_entry->configuration)) {
            $this->configuration = $configuration;
        } else {
            $this->configuration = null;
        }
    }

    private function serialize(\stdClass $configuration) {
        return json_encode($configuration);
    }

    private function deserialize(string $configuration) {
        return json_decode($configuration);
    }
}