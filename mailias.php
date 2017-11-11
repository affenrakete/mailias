<?php

/*
 * Copyright (C) 2017 Peter Siemer <admin@affenrakete.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 *  $config = [
 *    'mysqli' => [
 *      'user' => 'USERNAME',
 *      'pass' => 'USERPASS',
 *      'database' => 'USER_DB',
 *      'host' => 'localhost',
 *      'port' => '3306'
 *    ],
 *    'domain' => 'DOMAIN'
 *  ];
 */

namespace mailias;

/**
 * Description of mailias
 *
 * @author Peter
 */
class mailias {

    private $config = [];
    private $data = null;
    private $user = [];
    private $mysqli = null;
    private $error = [];
    private $notification = [];
    private $notificationID = 0;
    private $locked = true;

    public function setConfig($config = null) {

        if (!$this->checkConfig($config)) {
            $this->addNotification('error', 'system', __FUNCTION__, 'checkConfig failed');
            return false;
        }
        $this->config = $config;

        // Prüfen ob eine Datenbankverbindung aufgebaut werden kann.
        if (!$this->connect()) {
            $this->addNotification('error', 'system', __FUNCTION__, 'connect failed');
            return false;
        }

        return true;
    }

    public function checkUser($user_email = null) {

        if (!$this->checkEmail($user_email)) {
            $this->addNotification('error', 'system', __FUNCTION__, 'checkEmail failed');
            return false;
        }
        $this->user['email'] = $user_email;

        // Prüfen ob User in Datenbank existiert.
        if (!$this->readUser()) {
            $this->addNotification('error', 'system', __FUNCTION__, 'readUser failed');
            return false;
        }

        $this->locked = false;

        return true;
    }

    /*
     * addNote('case', 'class', 'function', 'text')
     * case -> info, error, debug 
     * class -> system, user
     * function_name -> __FUNCTION__
     * text
     */

    private function addNotification($case, $class, $function, $text) {

        $this->notification[$case][$class][++$this->notificationID] = [
            'function' => $function,
            'text' => $text
        ];
    }

    public function getNotification($case = null, $class = null) {

        $notification = [];

        if ($case == null AND $class == NULL) {
            $notification = $this->notification;
        } elseif (!empty($this->notification[$case][$class])) {
            $notification = $this->notification[$case][$class];
        }

        return $notification;
    }

    private function checkConfig($config = null) {

        if (!is_array($config)) {
            $this->addNotification('debug', 'system', __FUNCTION__, 'Config not set');
            return false;
        }

        if (!$this->checkInput($config['domain'], 'domain')) {
            $this->addNotification('debug', 'system', __FUNCTION__, 'checkDomain failed');
            return false;
        }

        return true;
    }

    private function checkEmail($email = null) {

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addNotification('debug', 'system', __FUNCTION__, 'Email not valid -> ' . $email);
            $this->addNotification('info', 'user', __FUNCTION__, 'Email ist nicht gültig.');
            return false;
        }
        return true;
    }

    private function checkInput($input = null, $type = null) {

        switch ($type) {
            case 'alias':
                $pattern = "/^(?=^.{5,30}$)([a-zA-Z0-9]+)(?:[\w]*[a-zA-Z0-9]+)$/";
                $user_info = "Alias ist ungültig.";
                /*
                 * Gesamt 5 - 30 Zeichen
                 * a-o A-Z 0-9
                 * Unterstrich möglich, jedoch nicht zu Begin oder Ende
                 */
                break;

            case 'description':
                $pattern = "/^([\w\ \-\.]){0,250}$/";
                $user_info = "Beschreibung ist nicht gültig.";
                /*
                 * Gesamt 0 - 250 Zeichen
                 * a-z A-Z 0-9
                 * Leerzeichen Bindestrich Unterstrich und Punkt sind erlaubt.
                 */
                break;

            case 'domain':
                $pattern = "/(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{1,63}(?<!-)\.)+[a-zA-Z]{2,63}$)/";
                $user_info = "Domain ist nmicht gültig.";
                break;

            default:
                return false;
        }

        if (preg_match($pattern, $input)) {
            return true;
        }

        $this->addNotification('debug', 'system', __FUNCTION__, $type . ' not valid -> ' . $input);
        $this->addNotification('info', 'user', __FUNCTION__, $user_info);
        return false;
    }

    private function checkUnlock() {

        if ($this->locked) {
            $this->addNotification('error', 'system', __FUNCTION__, 'locked');
            $this->addNotification('info', 'user', __FUNCTION__, 'System gesperrt.');

            return false;
        }
        return true;
    }

    public function getList() {
        return $this->data;
    }

    public function getShort() {
        return $this->user['short'];
    }

    private function connect() {

        $this->mysqli = new \mysqli($this->config['mysqli']['host'], $this->config['mysqli']['user'], $this->config['mysqli']['pass'], $this->config['mysqli']['database'], $this->config['mysqli']['port']);
        $this->mysqli->set_charset('utf8');

        if ($this->mysqli->connect_errno) {
            $this->addNotification('debug', 'system', __FUNCTION__, '(' . $this->mysqli->errno . ') ' . $this->mysqli->error);
            return false;
        }
        return true;
    }

    public function disconnect() {

        $this->mysqli->close();

        return true;
    }

    public function readList() {

        if (!$this->checkUnlock()) {
            return false;
        }

        $this->data = [];

        $sql = "SELECT * FROM mailias WHERE user_id = ? ORDER BY id ASC";

        $statement = $this->mysqli->prepare($sql);
        $statement->bind_param('i', $this->user['id']);
        $statement->execute();

        $result = $statement->get_result();

        while ($row = $result->fetch_assoc()) {
            $this->data[] = $row;
        }

        $result->free();

        return true;
    }

    private function readUser() {
        $sql = "SELECT id, short  FROM user WHERE email = ? AND activ = 1";

        $statement = $this->mysqli->prepare($sql);
        $statement->bind_param('s', $this->user['email']);
        $statement->execute();

        if ($result = $statement->get_result() AND $result->num_rows > 0) {
            $user = $result->fetch_assoc();

            $this->user['id'] = $user['id'];
            $this->user['short'] = $user['short'];

            $result->free();

            return true;
        }

        $result->free();

        return false;
    }

    public function insertAlias($alias = null, $receive = null, $description = null) {

        if (!$this->checkUnlock()) {
            return false;
        }

        // Prüfen Nutzereingaben
        $this->checkInput($alias, 'alias');
        $this->checkEmail($receive);
        $this->checkInput($description, 'description');

        // Komplette Alias Adresse zusammensetzen und prüfen
        $aliasEmail = \strtolower($this->user['short'] . "-" . $alias . "@" . $this->config['domain']);
        $this->checkEmail($aliasEmail);

        // Abbruch wenn Fehler aufgetreten sind.
        if ($this->notificationID > 0) {
            return false;
        }

        /*
         * Create .qmail
         * Weiterleitung durch Erstellung von .qmail Datei erzeugen
         */
        $this->createFile($alias, $receive);

        /*
         * Insert SQL
         * Daten in SQL eintragen
         */
        $this->createSql($receive, $description, $aliasEmail);

        $this->addNotification('info', 'user', __FUNCTION__, 'Email Adresse erfolgreich angelegt: ' . $alias);

        return true;
    }

    private function createFile($alias = null, $receive = null) {

        $createFile = \strtolower("/home/" . $this->config['user'] . "/.qmail-" . $this->user['short'] . "-" . $alias);

        // Prüfen ob Datei existiert und anschließend erstellen, wenn möglich.
        if (\file_exists($createFile)) {
            $this->addNotification('info', 'user', __FUNCTION__, 'Weiterleitung existiert bereits.');
        } elseif (!\file_put_contents($createFile, \strtolower($receive))) {
            $this->addNotification('info', 'user', __FUNCTION__, 'Weiterleitung konnte nicht angelegt werden.');
        }

        return true;
    }

    private function createSql($receive = null, $description = null, $aliasEmail = null) {

        $insert = [
            'alias' => $aliasEmail,
            'user_id' => $this->user['id'],
            'activ' => 1,
            'description' => $description,
            'receive' => \strtolower($receive),
            'decay' => '0'
        ];

        $sql = "INSERT INTO mailias (alias, user_id, activ, description, receive, decay) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?))";

        $statement = $this->mysqli->prepare($sql);
        $statement->bind_param('siissi', $insert['alias'], $insert['user_id'], $insert['activ'], $insert['description'], $insert['receive'], $insert['decay']);

        if (!$statement->execute()) {
            $this->addNotification('info', 'user', __FUNCTION__, 'Email Adresse konnte nicht erzeugt werden.');
            $this->addNotification('debug', 'system', __FUNCTION__, '(' . $this->mysqli->errno . ') ' . $this->mysqli->error);
        }

        return true;
    }

    public function delAlias($inputId = null) {

        if (!$this->checkUnlock()) {
            return false;
        }

        $toDelete = $this->getDestroyList($inputId);

        foreach ($toDelete as $delete) {
            /*
             * Delete .qmail
             * Weiterleitung durch Löschung von .qmail Datei deaktivieren
             */
            $this->destroyFile($delete);

            /*
             * Delete SQL
             * Daten aus SQL löschen
             */
            $this->destroySql($delete);

            $this->addNotification('info', 'user', __FUNCTION__, 'Email Adresse erfolgreich gelöscht: ' . $delete['alias']);
        }

        return true;
    }

    private function destroyFile($delete = null) {

        $deletePart = explode('@', $delete['alias']);
        $deleteFile = \strtolower("/home/" . $this->config['user'] . "/.qmail-" . $deletePart[0]);

        if (!\file_exists($deleteFile)) {
            $this->addNotification('info', 'user', __FUNCTION__, 'Weiterleitung existiert nicht.');
        } elseif (!\unlink($deleteFile)) {
            $this->addNotification('info', 'user', __FUNCTION__, 'Weiterleitung konnte nicht gelöscht werden.');
        }
    }

    private function destroySql($delete = null) {

        $sql = "DELETE FROM mailias WHERE id = ?";

        $statement = $this->mysqli->prepare($sql);
        $statement->bind_param('i', $delete['id']);

        if (!$statement->execute()) {
            $this->addNotification('info', 'user', __FUNCTION__, 'Email Adresse konnte nicht gelöscht werden.');
            $this->addNotification('debug', 'system', __FUNCTION__, '(' . $this->mysqli->errno . ') ' . $this->mysqli->error);
        }
    }

    private function getDestroyList($inputId = null) {

        $listId = [];
        $toDelete = [];

        foreach ($inputId as $id) {
            $listId[] = (int) $id;
        }

        // Benötigte Daten zum löschen aus Datenbank ziehen
        $sql = "SELECT id, alias FROM mailias WHERE id IN (" . implode(',', array_unique($listId)) . ") AND user_id = ? ORDER BY id ASC";

        $statement = $this->mysqli->prepare($sql);
        $statement->bind_param('i', $this->user['id']);
        $statement->execute();

        $result = $statement->get_result();

        while ($row = $result->fetch_assoc()) {
            $toDelete[] = $row;
        }

        $result->free();

        return $toDelete;
    }

}
