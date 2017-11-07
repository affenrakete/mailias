<?php

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

    protected $config = [];
    protected $data = null;
    protected $user = [];
    protected $mysqli = null;
    protected $error = [];
    protected $notification = [];
    protected $notificationID = 0;
    protected $unlock = false;
    public $deleted = [];

    public function setConfig($config = null) {

        if (!self::checkConfig($config)) {
            self::addNotification('error', 'system', __FUNCTION__, 'checkConfig failed');
            return false;
        }
        $this->config = $config;

        // Prüfen ob eine Datenbankverbindung aufgebaut werden kann.
        if (!self::connect()) {
            self::addNotification('error', 'system', __FUNCTION__, 'connect failed');
            return false;
        }
		
		return true;
    }
	
    public function checkUser($user_email = null) {

        if (!self::checkEmail($user_email)) {
            self::addNotification('error', 'system', __FUNCTION__, 'checkEmail failed');
            return false;
        }
        $this->user['email'] = $user_email;

        // Prüfen ob User in Datenbank existiert.
        if (!self::readUser()) {
            self::addNotification('error', 'system', __FUNCTION__, 'readUser failed');
            return false;
        }

        $this->unlock = true;
		
		return true;
    }	

    /*
     * addNote('case', 'class', 'function', 'text')
     * case -> info, error, debug 
     * class -> system, user
     * function_name -> __FUNCTION__
     * text
     */

    private static function addNotification($case, $class, $function, $text) {
        $this->notification[$case][$class][$this->notificationID] = [
            'function' => $function,
            'text' => $text
        ];
		
		$this->notificationID++;
    }

    public function getNotification($case = null, $class = null) {

		if($case == null AND $class == NULL)
			return $this->notification;
	
        $notification = [];

        if (!empty($this->notification[$case][$class])) {
            $notification = $this->notification[$case][$class];
        }
		
        return $notification;
    }

    private static function checkConfig($config = null) {
        if (!is_array($config)) {
            self::addNotification('debug', 'system', __FUNCTION__, 'Config not set');
            return false;
        }

        if (!self::checkDomain($config['domain'])) {
            self::addNotification('debug', 'system', __FUNCTION__, 'checkDomain failed');
            return false;
        }

        return true;
    }

    private static function checkEmail($email = null) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::addNotification('debug', 'system', __FUNCTION__, 'Email not valid -> ' . $email);
            return false;
        }
        return true;
    }

    private static function checkAlias($alias) {
        $pattern = "/^(?=^.{5,30}$)([a-zA-Z0-9]+)(?:[\w]*[a-zA-Z0-9]+)$/";
        /*
         * Gesamt 5 - 30 Zeichen
         * a-o A-Z 0-9
         * Unterstrich möglich, jedoch nicht zu Begin oder Ende
         */

        if (preg_match($pattern, $alias)) {
            return true;
        }

        self::addNotification('debug', 'system', __FUNCTION__, 'Alias not valid -> ' . $alias);
        return false;
    }

    private static function checkDomain($domain = null) {
        $pattern = "/(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{1,63}(?<!-)\.)+[a-zA-Z]{2,63}$)/";

        if (preg_match($pattern, $domain)) {
            return true;
        }

        self::addNotification('debug', 'system', __FUNCTION__, 'Domain not valid -> ' . $domain);
        return false;
    }

    private static function checkDescription($description) {
        $pattern = "/^([\w\ \-\.]){0,250}$/";
        /*
         * Gesamt 0 - 250 Zeichen
         * a-z A-Z 0-9
         * Leerzeichen Bindestrich Unterstrich und Punkt sind erlaubt.
         */

        if (preg_match($pattern, $description)) {
            return true;
        }

        self::addNotification('error', 'system', __FUNCTION__, 'Description not valid -> ' . $description);
        return false;
    }

    public function getList() {
        return $this->data;
    }
	
	public function getShort() {
        return $this->user['short'];
    }

    private static function connect() {
        $this->mysqli = new \mysqli($this->config['mysqli']['host'], $this->config['mysqli']['user'], $this->config['mysqli']['pass'], $this->config['mysqli']['database'], $this->config['mysqli']['port']);
        $this->mysqli->set_charset('utf8');

        if ($this->mysqli->connect_errno) {
            self::addNotification('debug', 'system', __FUNCTION__, '(' . $this->mysqli->errno . ') ' . $this->mysqli->error);
            return false;
        }
        return true;
    }

    public function disconnect() {
        $this->mysqli->close();
    }

    public function readList() {
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

    private static function readUser() {
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

        /*
         * Pre Check
         * Sind alle Informationen gegeben?
         */

        if (!$this->unlock) {
            self::addNotification('error', 'system', __FUNCTION__, 'unlock -> ' . $this->unlock);
        }

        // Prüfen Nutzereingaben
        if (!self::checkAlias($alias)) {
            self::addNotification('info', 'user', __FUNCTION__, 'Alias ist ungültig.');
        }

        if (!self::checkEmail($receive)) {
            self::addNotification('info', 'user', __FUNCTION__, 'Empfänger Email ist nicht gültig.');
        }

        if (!self::checkDescription($description)) {
            self::addNotification('info', 'user', __FUNCTION__, 'Beschreibung ist nicht gültig.');
        }

        // Komplette Alias Adresse zusammensetzen
        $aliasEmail = \strtolower($this->user['short'] . "-" . $alias . "@" . $this->config['domain']);

        // Komplette Alias Adresse checken
        if (!self::checkEmail($aliasEmail)) {
            self::addNotification('error', 'system', __FUNCTION__, 'aliasEmail ist ungültig.');
        }

        // Abbruch wenn Fehler aufgetreten sind.
        if ($this->notificationID > 0) {
            if (DEBUG) {
                print_r($this->notification);
            }

            return false;
        }

        /*
         * Create .qmail
         * Weiterleitung durch Erstellung von .qmail Datei erzeugen
         */

        $createFile = \strtolower("/home/" . $_SERVER['USER'] . "/.qmail-" . $this->user['short'] . "-" . $alias);

        // Prüfen ob Datei existiert und anschließend erstellen, wenn möglich.
        if (\file_exists($createFile)) {
            self::addNotification('info', 'user', __FUNCTION__, 'Weiterleitung existiert bereits.');
        } elseif (!\file_put_contents($createFile, \strtolower($receive))) {
            self::addNotification('info', 'user', __FUNCTION__, 'Weiterleitung konnte nicht angelegt werden.');
        }

        // Abbruch wenn Fehler aufgetreten sind.
        if ($this->notificationID > 0) {
            if (DEBUG) {
                print_r($this->notification);
            }

            return false;
        }

        /*
         * Insert SQL
         * Daten in SQL eintragen
         */

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
            self::addNotification('info', 'user', __FUNCTION__, 'Email Adresse konnte nicht erzeugt werden.');
            self::addNotification('debug', 'system', __FUNCTION__, '(' . $this->mysqli->errno . ') ' . $this->mysqli->error);

            return false;
        }

		self::addNotification('info', 'user', __FUNCTION__, 'Email Adresse erfolgreich angelegt: ' . $insert['alias']);
		
        return true;
    }

    public function delAlias($inputID = null) {

        $listID = [];
        $toDelete = [];

        if (!$this->unlock) {
            self::addNotification('error', 'system', __FUNCTION__, 'unlock -> ' . $this->unlock);
            return false;
        }

        foreach ($inputID as $var) {
            $listID[] = (int) $var;
        }
        $listID = implode(',', array_unique($listID));

        $sql = "SELECT id, alias FROM mailias WHERE id IN (" . $listID . ") AND user_id = ? ORDER BY id ASC";

        $statement = $this->mysqli->prepare($sql);
        $statement->bind_param('i', $this->user['id']);
        $statement->execute();

        $result = $statement->get_result();

        while ($row = $result->fetch_assoc()) {
            $toDelete[] = $row;
        }

        $result->free();

        /*
         * Do the loop
         */

        foreach ($toDelete as $delete) {

            /*
             * Delete .qmail
             * Weiterleitung durch Löschung von .qmail Datei deaktivieren
             */
            $deletePart = explode('@', $delete['alias']);
            $deleteFile = \strtolower("/home/" . $_SERVER['USER'] . "/.qmail-" . $deletePart[0]);

            if (!\file_exists($deleteFile)) {
                self::addNotification('info', 'user', __FUNCTION__, 'Weiterleitung existiert nicht.');
            } elseif (!\unlink($deleteFile)) {
                self::addNotification('info', 'user', __FUNCTION__, 'Weiterleitung konnte nicht gelöscht werden.');
            }

            /*
             * Delete SQL
             * Daten aus SQL löschen
             */

            $sql = "DELETE FROM mailias WHERE id = ?";

            $statement = $this->mysqli->prepare($sql);
            $statement->bind_param('i', $delete['id']);

            if (!$statement->execute()) {
                self::addNotification('info', 'user', __FUNCTION__, 'Email Adresse konnte nicht gelöscht werden.');
                self::addNotification('debug', 'system', __FUNCTION__, '(' . $this->mysqli->errno . ') ' . $this->mysqli->error);
            }

            if ($this->notificationID > 0) {
                if (DEBUG) {
                    print_r($this->notification);
                }

                return false;
            }

            $this->deleted[] = $delete['alias'];
        }
		
		foreach($this->deleted as $alias)
		{
			self::addNotification('info', 'user', __FUNCTION__, 'Email Adresse erfolgreich gelöscht: '. $alias);
		}

        return true;
    }

}
