<?php

namespace Leantime\Domain\Tickets\Repositories {

    use Illuminate\Contracts\Container\BindingResolutionException;
    use Leantime\Core\Eventhelpers as EventhelperCore;
    use Leantime\Core\Db as DbCore;
    use Leantime\Core\Language as LanguageCore;
    use Leantime\Domain\Users\Services\Users;
    use PDO;

    /**
     *
     */

    /**
     *
     */
    class Tickets
    {
        use EventhelperCore;

        /**
         * @access public
         * @var    object|null
         */
        public ?object $result = null;

        /**
         * @access public
         * @var    object|null
         */
        public ?object $tickets = null;

        /**
         * @access private
         * @var    DbCore
         */
        private DbCore $db;

        /**
         * @access public
         * @var    array
         */
        public array $statusClasses = array('3' => 'label-info', '1' => 'label-important', '4' => 'label-warning', '2' => 'label-warning', '0' => 'label-success', "-1" => "label-default");

        /**
         * @access public
         * @var    array
         */
        public array $statusListSeed = array(
            3 => array(
                "name" => 'status.new',
                "class" => 'label-info',
                "statusType" => "NEW",
                "kanbanCol" => true,
                "sortKey" => 1,
            ),
            1 => array(
                "name" => 'status.blocked',
                "class" => 'label-important',
                "statusType" => "INPROGRESS",
                "kanbanCol" => true,
                "sortKey" => 2,
            ),
            4 => array(
                "name" => 'status.in_progress',
                "class" => 'label-warning',
                "statusType" => "INPROGRESS",
                "kanbanCol" => true,
                "sortKey" => 3,
            ),
            2 => array(
                "name" => 'status.waiting_for_approval',
                "class" => 'label-warning',
                "statusType" => "INPROGRESS",
                "kanbanCol" => true,
                "sortKey" => 4,
            ),
            0 => array(
                "name" => 'status.done',
                "class" => 'label-success',
                "statusType" => "DONE",
                "kanbanCol" => true,
                "sortKey" => 5,
            ),
            -1 => array(
                "name" => 'status.archived',
                "class" => 'label-default',
                "statusType" => "DONE",
                "kanbanCol" => false,
                "sortKey" => 6,
            ),
        );

        /**
         * @access public
         * @var    array
         */
        public array $priority = array('1' => 'Critical', '2' => 'High', '3' => 'Medium', '4' => 'Low', '5' => 'Lowest');

        /**
         * @access public
         * @var    array
         */
        public array $efforts = array('0.5' => '< 2min', '1' => 'XS', '2' => 'S', '3' => "M", '5' => "L", '8' => "XL", '13' => "XXL");

        /**
         * @access public
         * @var    array
         */
        public array $type = array('task', 'subtask', 'story', 'bug');

        /**
         * @access public
         * @var    array
         */
        public array $typeIcons = array('story' => 'fa-book', 'task' => 'fa-check-square', 'subtask' => 'fa-diagram-successor', 'bug' => 'fa-bug');

        /**
         * @access private
         * @var    bool
         */
        private int|bool $page = 0;

        /**
         * @access public
         * @var    bool
         */
        public int|bool $rowsPerPage = 10;

        /**
         * @access private
         * @var    string
         */
        private string $limitSelect = "";

        /**
         * @access numPages
         * @var  string
         */
        public string $numPages = '';

        /**
         * @access public
         * @var    string
         */
        public string $sortBy = 'date';

        private LanguageCore $language;

        /**
         * __construct - get db connection
         *
         * @access public
         * @return void
         */
        public function __construct(DbCore $db, LanguageCore $language)
        {
            $this->db = $db;
            $this->language = $language;
        }

        /**
         * @param $projectId
         * @return array|array[]
         */
        /**
         * @param $projectId
         * @return array|array[]
         */
        /**
         * @param $projectId
         * @return array|array[]
         */
        /**
         * @param $projectId
         * @return array|array[]
         */
        public function getStateLabels($projectId = null): array
        {

            if ($projectId == null) {
                $projectId = $_SESSION['currentProject'];
            }

                $sql = "SELECT
						value
				FROM zp_settings WHERE `key` = :key
				LIMIT 1";

                $stmn = $this->db->database->prepare($sql);
                $stmn->bindvalue(':key', "projectsettings." . $projectId . ".ticketlabels", PDO::PARAM_STR);

                $stmn->execute();
                $values = $stmn->fetch();
                $stmn->closeCursor();

                $labels = array();

                $statusList = $this->statusListSeed;

                //Override the state values that are in the db
            if ($values !== false) {
                $statusList = array();

                foreach (unserialize($values['value']) as $key => $status) {
                    if (is_int($key)) {
                        //Backwards Compatibility with existing labels in db
                        //Prior to 2.1.9 labels were stored as <<statuskey>>:<<labelString>>
                        //Afterwards labelString was replaced with an array to include all different status attributes needed for custom status types
                        if (!is_array($status)) {
                            $statusList[$key] = $this->statusListSeed[$key];

                            if (is_array($statusList[$key]) && isset($statusList[$key]["name"])) {
                                $statusList[$key]["name"] = $status;
                            }
                        } else {
                            $statusList[$key] = $status;
                        }
                    }
                }
            } else {
                //If the values are not coming from the db, we need to translate the label strings
                foreach ($statusList as &$status) {
                    $status['name'] = $this->language->__($status['name']);
                }
            }

                //Sort by order number
                uasort($statusList, function ($a, $b) {
                    return $a['sortKey'] <=> $b['sortKey'];
                });

                $_SESSION["projectsettings"]["ticketlabels"] = $statusList;

                return $statusList;
        }

        /**
         * @return mixed
         */
        /**
         * @return mixed
         */
        /**
         * @return mixed
         */
        /**
         * @return mixed
         */
        public function getStatusList(): mixed
        {
            return $this->statusList;
        }

        /**
         * @param $projectId
         * @return string[]
         */
        /**
         * @param $projectId
         * @return string[]
         */
        /**
         * @param $projectId
         * @return string[]
         */
        /**
         * @param $projectId
         * @return string[]
         */
        public function getStatusListGroupedByType($projectId): array
        {

            //Ignoring status type NONE by design
            $statusByType = array(
                "DONE" => array(),
                "INPROGRESS" => array(),
                "NEW" => array(),
            );
            $states = $this->getStateLabels($projectId);

            foreach ($states as $key => $value) {
                $statusByType[$value["statusType"]][] = $key;
            }

            $doneQuery = "IN(" . implode(",", $statusByType["DONE"]) . ")";
            $inProgressQuery = "IN(" . implode(",", $statusByType["INPROGRESS"]) . ")";
            $newQuery = "IN(" . implode(",", $statusByType["NEW"]) . ")";
            $openTodos = "IN(" . implode(",", array_merge($statusByType["NEW"], $statusByType["INPROGRESS"])) . ")";

            if ($doneQuery == "IN()") {
                $doneQuery = "IN(FALSE)";
            }
            if ($inProgressQuery == "IN()") {
                $inProgressQuery = "IN(FALSE)";
            }
            if ($newQuery == "IN()") {
                $newQuery = "IN(FALSE)";
            }
            if ($openTodos == "IN()") {
                $openTodos = "IN(FALSE)";
            }

            $statusByTypeQuery = array(
                "DONE" => $doneQuery,
                "INPROGRESS" => $inProgressQuery,
                "NEW" => $newQuery,
                "ALLOPEN" => $openTodos,
            );
            return $statusByTypeQuery;
        }

        /**
         * getAll - get all Tickets, depending on userrole
         *
         * @access public
         * @param int $limit
         * @return false|array
         * @throws BindingResolutionException
         */
        public function getAll(int $limit = 9999): false|array
        {

            $id = $_SESSION['userdata']['id'];

            $values = $this->getUsersTickets($id, $limit);

            return $values;
        }

        /**
         * @param $id
         * @param $limit
         * @return array|false
         * @throws BindingResolutionException
         */
        /**
         * @param $id
         * @param $limit
         * @return array|false
         * @throws BindingResolutionException
         */
        /**
         * @param $id
         * @param $limit
         * @return array|false
         * @throws BindingResolutionException
         */
        /**
         * @param $id
         * @param $limit
         * @return array|false
         * @throws BindingResolutionException
         */
        public function getUsersTickets($id, $limit): false|array
        {
            $users = app()->make(Users::class);
            $user = $users->getUser($id);

            $sql = "SELECT
						ticket.id,
						ticket.headline,
						ticket.type,
						ticket.description,
						ticket.date,
						ticket.dateToFinish,
						ticket.projectId,
						ticket.priority,
						ticket.status,
						project.name as projectName,
						client.name as clientName,
						client.name as clientName,
						t1.id AS authorId,
						t1.firstname AS authorFirstname,
						t1.lastname AS authorLastname,
						t2.id AS editorId,
						t2.firstname AS editorFirstname,
						t2.lastname AS editorLastname
				FROM
				zp_tickets AS ticket
				LEFT JOIN zp_relationuserproject ON ticket.projectId = zp_relationuserproject.projectId
				LEFT JOIN zp_projects as project ON ticket.projectId = project.id
				LEFT JOIN zp_clients as client ON project.clientId = client.id
				LEFT JOIN zp_user AS t1 ON ticket.userId = t1.id
				LEFT JOIN zp_user AS t2 ON ticket.editorId = t2.id

				WHERE
				  (zp_relationuserproject.userId = :id
						        OR project.psettings = 'all'
				                OR (project.psettings = 'client' AND project.clientId = :clientId)
						        )

				  AND ticket.type <> 'milestone'
				GROUP BY ticket.id
				ORDER BY ticket.id DESC";

            if ($limit > -1) {
                $sql .= " LIMIT :limit";
            }

            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':id', $id, PDO::PARAM_STR);
            $stmn->bindValue(':clientId', $user['clientId'] ?? '', PDO::PARAM_STR);
            if ($limit > -1) {
                $stmn->bindValue(':limit', $limit, PDO::PARAM_INT);
            }
            $stmn->execute();
            $values = $stmn->fetchAll();
            $stmn->closeCursor();

            return $values;
        }


        /**
         * getAllBySearchCriteria - get Tickets by a serach term and/or a filter
         *
         * @access public
         * @param array  $searchCriteria
         * @param string $sort
         * @param null   $limit
         * @return array | bool
         */
        public function getAllBySearchCriteria(array $searchCriteria, string $sort = 'standard', $limit = null): bool|array
        {

            $query = "SELECT
							zp_tickets.id,
							zp_tickets.headline,
							zp_tickets.description,
							zp_tickets.date,
							zp_tickets.sprint,
							zp_sprints.name as sprintName,
							zp_tickets.storypoints,
							zp_tickets.sortindex,
							zp_tickets.dateToFinish,
							zp_tickets.projectId,
							zp_tickets.priority,
							zp_tickets.type,
							zp_tickets.status,
							zp_tickets.tags,
							zp_tickets.editorId,
							zp_tickets.dependingTicketId,
							zp_tickets.milestoneid,
							zp_tickets.planHours,
							zp_tickets.editFrom,
							zp_tickets.editTo,
							zp_tickets.hourRemaining,
							(SELECT ROUND(SUM(hours), 2) FROM zp_timesheets WHERE zp_tickets.id = zp_timesheets.ticketId) AS bookedHours,
							zp_projects.name AS projectName,
							zp_clients.name AS clientName,
							zp_clients.id AS clientId,
							t1.id AS authorId,
							t1.lastname AS authorLastname,
							t1.firstname AS authorFirstname,
							t1.profileId AS authorProfileId,
							t2.firstname AS editorFirstname,
							t2.lastname AS editorLastname,
							t2.profileId AS editorProfileId,
							milestone.headline AS milestoneHeadline,
							IF((milestone.tags IS NULL OR milestone.tags = ''), 'var(--grey)', milestone.tags) AS milestoneColor,
							COUNT(DISTINCT zp_comment.id) AS commentCount,
							COUNT(DISTINCT zp_file.id) AS fileCount,
							COUNT(DISTINCT subtasks.id) AS subtaskCount,
							parent.headline AS parentHeadline
						FROM
							zp_tickets
						LEFT JOIN zp_relationuserproject USING (projectId)
						LEFT JOIN zp_projects ON zp_tickets.projectId = zp_projects.id
						LEFT JOIN zp_clients ON zp_projects.clientId = zp_clients.id
						LEFT JOIN zp_user AS t1 ON zp_tickets.userId = t1.id
						LEFT JOIN zp_user AS t2 ON zp_tickets.editorId = t2.id
						LEFT JOIN zp_user AS requestor ON requestor.id = :requestorId
						LEFT JOIN zp_comment ON zp_tickets.id = zp_comment.moduleId and zp_comment.module = 'ticket'
						LEFT JOIN zp_file ON zp_tickets.id = zp_file.moduleId and zp_file.module = 'ticket'
						LEFT JOIN zp_sprints ON zp_tickets.sprint = zp_sprints.id
						LEFT JOIN zp_tickets AS milestone ON zp_tickets.milestoneid = milestone.id AND zp_tickets.milestoneid > 0 AND milestone.type = 'milestone'
						LEFT JOIN zp_tickets AS parent ON zp_tickets.dependingTicketId = parent.id
						LEFT JOIN zp_tickets AS subtasks ON zp_tickets.id = subtasks.dependingTicketId AND subtasks.dependingTicketId > 0
						LEFT JOIN zp_timesheets AS timesheets ON zp_tickets.id = timesheets.ticketId
						WHERE
						    (   zp_relationuserproject.userId = :userId
						        OR zp_projects.psettings = 'all'
				                OR (zp_projects.psettings = 'client' AND zp_projects.clientId = :clientId)
						        OR (requestor.role >= 40)
						        )";

            $query .= " AND zp_tickets.type <> 'milestone'";


            if (isset($searchCriteria["currentProject"]) && $searchCriteria["currentProject"]  != "") {
                $query .= " AND zp_tickets.projectId = :projectId";
            }


            if (isset($searchCriteria["users"]) && $searchCriteria["users"]  != "") {
                $editorIdIn = DbCore::arrayToPdoBindingString("users", count(explode(",", $searchCriteria["users"])));
                $query .= " AND zp_tickets.editorId IN(" . $editorIdIn . ")";
            }

            if (isset($searchCriteria["milestone"]) && $searchCriteria["milestone"]  != "") {
                $milestoneIn = DbCore::arrayToPdoBindingString("milestone", count(explode(",", $searchCriteria["milestone"])));
                $query .= " AND zp_tickets.milestoneid IN(" . $milestoneIn . ")";
            }

            if (isset($searchCriteria["status"]) && $searchCriteria["status"]  == "all") {
                $query .= " ";
            } elseif (isset($searchCriteria["status"]) && $searchCriteria["status"]  != "") {
                $statusArray = explode(",", $searchCriteria['status']);

                if (array_search("not_done", $statusArray) !== false) {
                    //Project Id needs to be set to search for not_done due to custom done states across projects
                    if ($searchCriteria["currentProject"] != "") {
                        $statusLabels = $this->getStateLabels($searchCriteria["currentProject"]);

                        $statusList = array();
                        foreach ($statusLabels as $key => $status) {
                            if ($status['statusType'] !== "DONE") {
                                $statusList[] = $key;
                            }
                        }

                        $query .= " AND zp_tickets.status IN(" . implode(",", $statusList) . ")";
                    }
                } else {
                    $statusIn = DbCore::arrayToPdoBindingString(
                        "status",
                        count(explode(",", $searchCriteria["status"]))
                    );
                    $query .= " AND zp_tickets.status IN(" . $statusIn . ")";
                }
            } else {
                $query .= " AND zp_tickets.status <> -1";
            }

            if (isset($searchCriteria["type"]) && $searchCriteria["type"]  != "") {
                $typeIn = DbCore::arrayToPdoBindingString("type", count(explode(",", strtolower($searchCriteria["type"]))));
                $query .= " AND LOWER(zp_tickets.type) IN(" . $typeIn . ")";
            }

            if (isset($searchCriteria["priority"]) && $searchCriteria["priority"]  != "") {
                $priorityIn = DbCore::arrayToPdoBindingString("priority", count(explode(",", strtolower($searchCriteria["priority"]))));
                $query .= " AND LOWER(zp_tickets.priority) IN(" . $priorityIn . ")";
            }

            if (isset($searchCriteria["term"]) && $searchCriteria["term"]  != "") {
                $query .= " AND (FIND_IN_SET(:termStandard, zp_tickets.tags) OR zp_tickets.headline LIKE :termWild OR zp_tickets.description LIKE :termWild OR zp_tickets.id LIKE :termWild)";
            }

            if (isset($searchCriteria["sprint"]) && $searchCriteria["sprint"]  > 0 && $searchCriteria["sprint"]  != "all") {
                $sprintIn = DbCore::arrayToPdoBindingString("sprint", count(explode(",", $searchCriteria["sprint"])));
                $query .= " AND zp_tickets.sprint IN(" . $sprintIn . ")";
            }

            if (isset($searchCriteria["sprint"]) && $searchCriteria["sprint"]  == "backlog") {
                $query .= " AND (zp_tickets.sprint IS NULL OR zp_tickets.sprint = '' OR zp_tickets.sprint = -1)";
            }

            $query .= " GROUP BY zp_tickets.id ";

            if ($sort == "standard") {
                $query .= " ORDER BY zp_tickets.sortindex ASC, zp_tickets.id DESC";
            } elseif ($sort == "kanbansort") {
                $query .= " ORDER BY zp_tickets.kanbanSortIndex ASC, zp_tickets.id DESC";
            } elseif ($sort == "duedate") {
                $query .= " ORDER BY (zp_tickets.dateToFinish = '0000-00-00 00:00:00'), zp_tickets.dateToFinish ASC, zp_tickets.sortindex ASC, zp_tickets.id DESC";
            } elseif ($sort == "date") {
                $query .= " ORDER BY zp_tickets.date DESC, zp_tickets.sortindex ASC, zp_tickets.id DESC";
            }

            if ($limit !== null && $limit > 0) {
                $query .= " LIMIT :limit";
            }

            $stmn = $this->db->database->prepare($query);

            if (isset($searchCriteria["currentUser"])) {
                $stmn->bindValue(':userId', $searchCriteria["currentUser"], PDO::PARAM_INT);
            } else {
                $stmn->bindValue(':userId', $_SESSION['userdata']['id'], PDO::PARAM_INT);
            }

            if (isset($searchCriteria["currentClient"])) {
                $stmn->bindValue(':clientId', $searchCriteria["currentClient"], PDO::PARAM_INT);
            } else {
                $stmn->bindValue(':clientId', $_SESSION['userdata']['clientId'], PDO::PARAM_INT);
            }


            if (isset($searchCriteria["currentProject"]) && $searchCriteria["currentProject"] != "") {
                $stmn->bindValue(':projectId', $searchCriteria["currentProject"], PDO::PARAM_INT);
            }


            if (isset($searchCriteria["milestone"]) && $searchCriteria["milestone"]  != "") {
                foreach (explode(",", $searchCriteria["milestone"]) as $key => $milestone) {
                    $stmn->bindValue(":milestone" . $key, $milestone, PDO::PARAM_STR);
                }
            }

            if (isset($searchCriteria["type"]) && $searchCriteria["type"]  != "") {
                foreach (explode(",", $searchCriteria["type"]) as $key => $type) {
                    $stmn->bindValue(":type" . $key, $type, PDO::PARAM_STR);
                }
            }

            if (isset($searchCriteria["priority"]) && $searchCriteria["priority"]  != "") {
                foreach (explode(",", $searchCriteria["priority"]) as $key => $priority) {
                    $stmn->bindValue(":priority" . $key, $priority, PDO::PARAM_STR);
                }
            }

            if (isset($searchCriteria["users"]) && $searchCriteria["users"]  != "") {
                foreach (explode(",", $searchCriteria["users"]) as $key => $user) {
                    $stmn->bindValue(":users" . $key, $user, PDO::PARAM_STR);
                }
            }

            if (isset($searchCriteria['status']) && $searchCriteria["status"]  != "all") {
                $statusArray = explode(",", $searchCriteria['status']);
                if ($searchCriteria["status"] != "" && array_search("not_done", $statusArray) === false) {
                    foreach (explode(",", $searchCriteria["status"]) as $key => $status) {
                        $stmn->bindValue(":status" . $key, $status, PDO::PARAM_STR);
                    }
                }
            }

            if (isset($searchCriteria["sprint"]) && $searchCriteria["sprint"]  > 0 && $searchCriteria["sprint"]  != "all") {
                foreach (explode(",", $searchCriteria["sprint"]) as $key => $sprint) {
                    $stmn->bindValue(":sprint" . $key, $sprint, PDO::PARAM_STR);
                }
            }

            if (isset($searchCriteria["term"]) && $searchCriteria["term"]  != "") {
                $termWild = "%" . $searchCriteria["term"] . "%";
                $stmn->bindValue(':termWild', $termWild, PDO::PARAM_STR);
                $stmn->bindValue(':termStandard', $searchCriteria["term"], PDO::PARAM_STR);
            }

            if ($limit !== null && $limit > 0) {
                $stmn->bindValue(':limit', $limit, PDO::PARAM_INT);
            }

            $stmn->bindValue(':requestorId', $_SESSION['userdata']['id'], PDO::PARAM_INT);

            $stmn->execute();

            $values = $stmn->fetchAll();
            $stmn->closeCursor();

            return $values;
        }

        /**
         * @param $projectId
         * @return array|false
         */
        /**
         * @param $projectId
         * @return array|false
         */
        /**
         * @param $projectId
         * @return array|false
         */
        /**
         * @param $projectId
         * @return array|false
         */
        public function getAllByProjectId($projectId): false|array
        {

            $query = "SELECT
						zp_tickets.id,
						zp_tickets.headline,
						zp_tickets.type,
						zp_tickets.description,
						zp_tickets.date,
						zp_tickets.dateToFinish,
						zp_tickets.projectId,
						zp_tickets.priority,
						zp_tickets.status,
						zp_tickets.sprint,
						zp_tickets.storypoints,
						zp_tickets.hourRemaining,
						zp_tickets.acceptanceCriteria,
						zp_tickets.userId,
						zp_tickets.editorId,
						zp_tickets.planHours,
						zp_tickets.tags,
						zp_tickets.url,
						zp_tickets.editFrom,
						zp_tickets.editTo,
						zp_tickets.dependingTicketId,
						zp_tickets.milestoneid,
						zp_projects.name AS projectName,
						zp_clients.name AS clientName,
						zp_user.firstname AS userFirstname,
						zp_user.lastname AS userLastname,
						t3.firstname AS editorFirstname,
						t3.lastname AS editorLastname
					FROM
						zp_tickets LEFT JOIN zp_projects ON zp_tickets.projectId = zp_projects.id
						LEFT JOIN zp_clients ON zp_projects.clientId = zp_clients.id
						LEFT JOIN zp_user ON zp_tickets.userId = zp_user.id
						LEFT JOIN zp_user AS t3 ON zp_tickets.editorId = t3.id
					WHERE
						zp_tickets.projectId = :projectId
					GROUP BY
						zp_tickets.id";


            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);

            $stmn->execute();
            $values = $stmn->fetchAll(PDO::FETCH_CLASS, '\Leantime\Domain\Tickets\Models\Tickets');
            $stmn->closeCursor();

            return $values;
        }

        /**
         * @param $projectId
         * @return array|false
         */
        /**
         * @param $projectId
         * @return array|false
         */
        /**
         * @param $projectId
         * @return array|false
         */
        /**
         * @param $projectId
         * @return array|false
         */
        public function getTags($projectId): false|array
        {

            $query = "SELECT
						zp_tickets.tags
					FROM
						zp_tickets LEFT JOIN zp_projects ON zp_tickets.projectId = zp_projects.id
					WHERE
						zp_tickets.projectId = :projectId AND zp_tickets.type <> 'milestone'";


            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);

            $stmn->execute();
            $values = $stmn->fetchAll();
            $stmn->closeCursor();

            return $values;
        }

        /**
         * getTicket - get a specific Ticket depending on the role
         *
         * @access public
         * @param  $id
         * @return \Leantime\Domain\Tickets\Models\Tickets|bool
         */
        public function getTicket($id): \Leantime\Domain\Tickets\Models\Tickets|bool
        {

            $query = "SELECT
						zp_tickets.id,
						zp_tickets.headline,
						zp_tickets.type,
						zp_tickets.description,
						zp_tickets.date,
						zp_tickets.dateToFinish,
						zp_tickets.projectId,
						zp_tickets.priority,
						zp_tickets.status,
						zp_tickets.sprint,
						zp_tickets.storypoints,
						zp_tickets.hourRemaining,
						zp_tickets.acceptanceCriteria,
						zp_tickets.userId,
						zp_tickets.editorId,
						zp_tickets.planHours,
						zp_tickets.tags,
						zp_tickets.url,
						zp_tickets.editFrom,
						zp_tickets.editTo,
						zp_tickets.dependingTicketId,
						zp_tickets.milestoneid,
						zp_projects.name AS projectName,
						zp_clients.name AS clientName,
						zp_user.firstname AS userFirstname,
						zp_user.lastname AS userLastname,
						t3.firstname AS editorFirstname,
						t3.lastname AS editorLastname,
						parent.headline AS parentHeadline
					FROM
						zp_tickets LEFT JOIN zp_projects ON zp_tickets.projectId = zp_projects.id
						LEFT JOIN zp_clients ON zp_projects.clientId = zp_clients.id
						LEFT JOIN zp_user ON zp_tickets.userId = zp_user.id
						LEFT JOIN zp_user AS t3 ON zp_tickets.editorId = t3.id
					    Left JOIN zp_tickets AS parent on zp_tickets.dependingTicketId = parent.id
					WHERE
						zp_tickets.id = :ticketId
					GROUP BY
						zp_tickets.id
					LIMIT 1";


            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':ticketId', $id, PDO::PARAM_INT);

            $stmn->execute();
            $values = $stmn->fetchObject('\Leantime\Domain\Tickets\Models\Tickets');
            $stmn->closeCursor();

            return $values;
        }

        /**
         * @param $id
         * @return array|false
         */
        /**
         * @param $id
         * @return array|false
         */
        /**
         * @param $id
         * @return array|false
         */
        /**
         * @param $id
         * @return array|false
         */
        public function getAllSubtasks($id): false|array
        {

            $query = "SELECT
						zp_tickets.id,
						zp_tickets.headline,
						zp_tickets.type,
						zp_tickets.description,
						zp_tickets.date,
						DATE_FORMAT(zp_tickets.date, '%Y,%m,%e') AS timelineDate,
						DATE_FORMAT(zp_tickets.dateToFinish, '%Y,%m,%e') AS timelineDateToFinish,
						zp_tickets.dateToFinish,
						zp_tickets.projectId,
						zp_tickets.priority,
						zp_tickets.status,
						zp_tickets.sprint,
						zp_tickets.storypoints,
						IFNULL(zp_tickets.hourRemaining, 0) AS hourRemaining,
						zp_tickets.acceptanceCriteria,
						zp_tickets.userId,
						zp_tickets.editorId,
						IFNULL(zp_tickets.planHours, 0) AS planHours,
						zp_tickets.tags,
						zp_tickets.url,
						zp_tickets.editFrom,
						zp_tickets.editTo,
						zp_tickets.dependingTicketId,
						zp_tickets.milestoneid,
						zp_projects.name AS projectName,
						zp_clients.name AS clientName,
						zp_user.firstname AS userFirstname,
						zp_user.lastname AS userLastname,
						t3.firstname AS editorFirstname,
						t3.lastname AS editorLastname
					FROM
						zp_tickets LEFT JOIN zp_projects ON zp_tickets.projectId = zp_projects.id
						LEFT JOIN zp_clients ON zp_projects.clientId = zp_clients.id
						LEFT JOIN zp_user ON zp_tickets.userId = zp_user.id
						LEFT JOIN zp_user AS t3 ON zp_tickets.editorId = t3.id
					WHERE
						zp_tickets.dependingTicketId = :ticketId
					GROUP BY
						zp_tickets.id ORDER BY zp_tickets.date DESC";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':ticketId', $id, PDO::PARAM_INT);

            $stmn->execute();
            $values = $stmn->fetchAll();
            $stmn->closeCursor();

            return $values;
        }

        /**
         * @param \Leantime\Domain\Tickets\Models\Tickets $ticket
         * @param $projectId
         * @return array|false
         */
        /**
         * @param \Leantime\Domain\Tickets\Models\Tickets $ticket
         * @param $projectId
         * @return array|false
         */
        /**
         * @param \Leantime\Domain\Tickets\Models\Tickets $ticket
         * @param $projectId
         * @return array|false
         */
        /**
         * @param \Leantime\Domain\Tickets\Models\Tickets $ticket
         * @param $projectId
         * @return array|false
         */
        public function getAllPossibleParents(\Leantime\Domain\Tickets\Models\Tickets $ticket, $projectId): false|array
        {

            $query = "SELECT
						zp_tickets.id,
						zp_tickets.headline,
						zp_tickets.type,
						zp_tickets.description,
						zp_tickets.date,
						DATE_FORMAT(zp_tickets.date, '%Y,%m,%e') AS timelineDate,
						DATE_FORMAT(zp_tickets.dateToFinish, '%Y,%m,%e') AS timelineDateToFinish,
						zp_tickets.dateToFinish,
						zp_tickets.projectId,
						zp_tickets.priority,
						zp_tickets.status,
						zp_tickets.sprint,
						zp_tickets.storypoints,
						IFNULL(zp_tickets.hourRemaining, 0) AS hourRemaining,
						zp_tickets.acceptanceCriteria,
						zp_tickets.userId,
						zp_tickets.editorId,
						IFNULL(zp_tickets.planHours, 0) AS planHours,
						zp_tickets.tags,
						zp_tickets.url,
						zp_tickets.editFrom,
						zp_tickets.editTo,
						zp_tickets.dependingTicketId,
						zp_tickets.milestoneid,
						zp_projects.name AS projectName,
						zp_clients.name AS clientName,
						zp_user.firstname AS userFirstname,
						zp_user.lastname AS userLastname,
						t3.firstname AS editorFirstname,
						t3.lastname AS editorLastname
					FROM
						zp_tickets LEFT JOIN zp_projects ON zp_tickets.projectId = zp_projects.id
						LEFT JOIN zp_clients ON zp_projects.clientId = zp_clients.id
						LEFT JOIN zp_user ON zp_tickets.userId = zp_user.id
						LEFT JOIN zp_user AS t3 ON zp_tickets.editorId = t3.id
					WHERE
						zp_tickets.id <> :ticketId
					    AND zp_tickets.type <> 'milestone'
					    AND (zp_tickets.dependingTicketId <> :ticketId OR zp_tickets.dependingTicketId IS NULL)
                    ";

            if ($projectId !== 0) {
                $query .= " AND zp_tickets.projectId = :projectId";
            }

                    $query .= " GROUP BY
						zp_tickets.id ORDER BY zp_tickets.date DESC";

            $stmn = $this->db->database->prepare($query);

            $stmn->bindValue(':ticketId', $ticket->id, PDO::PARAM_INT);
            $stmn->bindValue(':dependingId', $ticket->dependingTicketId, PDO::PARAM_INT);

            if ($projectId !== 0) {
                $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);
            }

            $stmn->execute();
            $values = $stmn->fetchAll(PDO::FETCH_CLASS, 'Leantime\Domain\Tickets\Models\Tickets');
            $stmn->closeCursor();

            return $values;
        }

        /**
         * Gets all tasks grouped around milestones for timeline views
         *
         * @param array  $searchCriteria
         * @param string $sort
         * @return array|false
         */
        public function getAllMilestones(array $searchCriteria, string $sort = 'standard'): false|array
        {

            $statusGroups = $this->getStatusListGroupedByType($searchCriteria["currentProject"] ?? $_SESSION['currentProject']);


            $query = "SELECT
						zp_tickets.id,
						zp_tickets.headline,
						zp_tickets.type,
						zp_tickets.description,
						zp_tickets.date,
						DATE_FORMAT(zp_tickets.date, '%Y,%m,%e') AS timelineDate,
						DATE_FORMAT(zp_tickets.dateToFinish, '%Y,%m,%e') AS timelineDateToFinish,
						zp_tickets.dateToFinish,
						zp_tickets.projectId,
						zp_tickets.priority,
						zp_tickets.status,
						zp_tickets.sprint,
						zp_tickets.storypoints,
						zp_tickets.hourRemaining,
						zp_tickets.acceptanceCriteria,
						depMilestone.headline AS milestoneHeadline,
						IF((depMilestone.tags IS NULL OR depMilestone.tags = ''), 'var(--grey)', depMilestone.tags) AS milestoneColor,
						zp_tickets.userId,
						zp_tickets.editorId,
						zp_tickets.planHours,
						IF((zp_tickets.tags IS NULL OR zp_tickets.tags = ''), 'var(--grey)', zp_tickets.tags) AS tags,
						zp_tickets.url,
						zp_tickets.editFrom,
						zp_tickets.editTo,
						zp_tickets.sortIndex,
						zp_tickets.dependingTicketId,
						zp_tickets.milestoneid,
						zp_projects.name AS projectName,
						zp_clients.name AS clientName,
						zp_user.firstname AS userFirstname,
						zp_user.lastname AS userLastname,
						t3.firstname AS editorFirstname,
						t3.lastname AS editorLastname,
						t3.profileId AS editorProfileId,

						(SELECT SUM(progressSub.planHours) FROM zp_tickets as progressSub WHERE progressSub.milestoneid = zp_tickets.id) AS planHours,
						(SELECT SUM(progressSub.hourRemaining) FROM zp_tickets as progressSub WHERE progressSub.milestoneid = zp_tickets.id) AS hourRemaining,
						SUM(ROUND(timesheets.hours, 2)) AS bookedHours,

						COUNT(DISTINCT progressTickets.id) AS allTickets,

						(SELECT (
                            CASE WHEN
                              COUNT(DISTINCT progressSub.id) > 0
                            THEN
                              ROUND(
                                (
                                  SUM(CASE WHEN progressSub.status " . $statusGroups["DONE"] . " THEN IF(progressSub.storypoints = 0, 3, progressSub.storypoints) ELSE 0 END) /
                                  SUM(IF(progressSub.storypoints = 0, 3, progressSub.storypoints))
                                ) *100)
                            ELSE
                              0
                            END) AS percentDone
                        FROM zp_tickets AS progressSub WHERE progressSub.milestoneid = zp_tickets.id AND progressSub.type <> 'milestone') AS percentDone
					FROM
						zp_tickets
						LEFT JOIN zp_projects ON zp_tickets.projectId = zp_projects.id
						LEFT JOIN zp_tickets AS depMilestone ON zp_tickets.milestoneid = depMilestone.id
						LEFT JOIN zp_clients ON zp_projects.clientId = zp_clients.id
						LEFT JOIN zp_user ON zp_tickets.userId = zp_user.id
						LEFT JOIN zp_user AS t3 ON zp_tickets.editorId = t3.id
						LEFT JOIN zp_tickets AS progressTickets ON progressTickets.milestoneid = zp_tickets.id AND progressTickets.type <> 'milestone'
						LEFT JOIN zp_timesheets AS timesheets ON progressTickets.id = timesheets.ticketId
						WHERE (zp_projects.state <> -1 OR zp_projects.state IS NULL)";


            if (isset($searchCriteria["currentProject"]) && $searchCriteria["currentProject"]  != "") {
                $query .= " AND zp_tickets.projectId = :projectId";
            }

            if (isset($searchCriteria["currentClient"]) && $searchCriteria["currentClient"]  != "" && $searchCriteria["currentClient"]  != 0) {
                $query .= " AND zp_projects.clientId = :clientId";
            }

            if (isset($searchCriteria["users"]) && $searchCriteria["users"]  != "") {
                $editorIdIn = DbCore::arrayToPdoBindingString("users", count(explode(",", $searchCriteria["users"])));
                $query .= " AND zp_tickets.editorId IN(" . $editorIdIn . ")";
            }

            if (isset($searchCriteria["milestone"]) && $searchCriteria["milestone"]  != "") {
                $milestoneIn = DbCore::arrayToPdoBindingString("milestone", count(explode(",", $searchCriteria["milestone"])));
                $query .= " AND zp_tickets.milestoneid IN(" . $milestoneIn . ")";
            }

            if (isset($searchCriteria["status"]) && $searchCriteria["status"]  == "all") {
                $query .= " ";
            } elseif (isset($searchCriteria["status"]) && $searchCriteria["status"]  != "") {
                $statusArray = explode(",", $searchCriteria['status']);

                if (array_search("not_done", $statusArray) !== false) {
                    //Project Id needs to be set to search for not_done due to custom done states across projects
                    if ($searchCriteria["currentProject"] != "") {
                        $statusLabels = $this->getStateLabels($searchCriteria["currentProject"]);

                        $statusList = array();
                        foreach ($statusLabels as $key => $status) {
                            if ($status['statusType'] !== "DONE") {
                                $statusList[] = $key;
                            }
                        }

                        $query .= " AND zp_tickets.status IN(" . implode(",", $statusList) . ")";
                    }
                } else {
                    $statusIn = DbCore::arrayToPdoBindingString(
                        "status",
                        count(explode(",", $searchCriteria["status"]))
                    );
                    $query .= " AND zp_tickets.status IN(" . $statusIn . ")";
                }
            } else {
                $query .= " AND zp_tickets.status <> -1";
            }

            if (isset($searchCriteria["type"]) && $searchCriteria["type"]  != "") {
                $typeIn = DbCore::arrayToPdoBindingString("type", count(explode(",", strtolower($searchCriteria["type"]))));
                $query .= " AND LOWER(zp_tickets.type) IN(" . $typeIn . ")";
            }

            if (isset($searchCriteria["priority"]) && $searchCriteria["priority"]  != "") {
                $priorityIn = DbCore::arrayToPdoBindingString("priority", count(explode(",", strtolower($searchCriteria["priority"]))));
                $query .= " AND LOWER(zp_tickets.priority) IN(" . $priorityIn . ")";
            }

            if (isset($searchCriteria["term"]) && $searchCriteria["term"]  != "") {
                $query .= " AND (FIND_IN_SET(:termStandard, zp_tickets.tags) OR zp_tickets.headline LIKE :termWild OR zp_tickets.description LIKE :termWild OR zp_tickets.id LIKE :termWild)";
            }

            if (isset($searchCriteria["sprint"]) && $searchCriteria["sprint"]  > 0 && $searchCriteria["sprint"]  != "all") {
                $sprintIn = DbCore::arrayToPdoBindingString("sprint", count(explode(",", $searchCriteria["sprint"])));
                $query .= " AND (zp_tickets.sprint IN(" . $sprintIn . ") OR zp_tickets.type = 'milestone')";
            }

            if (isset($searchCriteria["sprint"]) && $searchCriteria["sprint"]  == "backlog") {
                $query .= " AND (zp_tickets.sprint IS NULL OR zp_tickets.sprint = '' OR zp_tickets.sprint = -1 OR zp_tickets.type = 'milestone')";
            }

            $query .= "	GROUP BY
						zp_tickets.id, progressTickets.milestoneid";

            if ($sort == "standard") {
                $query .= " ORDER BY zp_tickets.sortindex ASC, zp_tickets.id DESC";
            } elseif ($sort == "kanbansort") {
                $query .= " ORDER BY zp_tickets.kanbanSortIndex ASC, zp_tickets.id DESC";
            } elseif ($sort == "duedate") {
                $query .= " ORDER BY (zp_tickets.dateToFinish = '0000-00-00 00:00:00'), zp_tickets.dateToFinish ASC, zp_tickets.sortindex ASC, zp_tickets.id DESC";
            } elseif ($sort == "date") {
                $query .= " ORDER BY zp_tickets.date DESC, zp_tickets.sortindex ASC, zp_tickets.id DESC";
            }

            $stmn = $this->db->database->prepare($query);

            if (isset($searchCriteria["currentProject"]) && $searchCriteria["currentProject"] != "") {
                $stmn->bindValue(':projectId', $searchCriteria["currentProject"], PDO::PARAM_INT);
            }

            if (isset($searchCriteria["currentClient"]) && $searchCriteria["currentClient"]  != "" && $searchCriteria["currentClient"]  != 0) {
                $stmn->bindValue(':clientId', $searchCriteria["currentClient"], PDO::PARAM_INT);
            }

            if (isset($searchCriteria["users"]) && $searchCriteria["users"]  != "") {
                foreach (explode(",", $searchCriteria["users"]) as $key => $user) {
                    $stmn->bindValue(":users" . $key, $user, PDO::PARAM_STR);
                }
            }

            if (isset($searchCriteria["milestone"]) && $searchCriteria["milestone"]  != "") {
                foreach (explode(",", $searchCriteria["milestone"]) as $key => $milestone) {
                    $stmn->bindValue(":milestone" . $key, $milestone, PDO::PARAM_STR);
                }
            }

            if (isset($searchCriteria['status']) && $searchCriteria["status"]  != "all") {
                $statusArray = explode(",", $searchCriteria['status']);
                if ($searchCriteria["status"] != "" && array_search("not_done", $statusArray) === false) {
                    foreach (explode(",", $searchCriteria["status"]) as $key => $status) {
                        $stmn->bindValue(":status" . $key, $status, PDO::PARAM_STR);
                    }
                }
            }

            if (isset($searchCriteria["type"]) && $searchCriteria["type"]  != "") {
                foreach (explode(",", $searchCriteria["type"]) as $key => $type) {
                    $stmn->bindValue(":type" . $key, $type, PDO::PARAM_STR);
                }
            }

            if (isset($searchCriteria["priority"]) && $searchCriteria["priority"]  != "") {
                foreach (explode(",", $searchCriteria["priority"]) as $key => $priority) {
                    $stmn->bindValue(":priority" . $key, $priority, PDO::PARAM_STR);
                }
            }

            if (isset($searchCriteria["term"]) && $searchCriteria["term"]  != "") {
                $termWild = "%" . $searchCriteria["term"] . "%";
                $stmn->bindValue(':termWild', $termWild, PDO::PARAM_STR);
                $stmn->bindValue(':termStandard', $searchCriteria["term"], PDO::PARAM_STR);
            }

            if (isset($searchCriteria["sprint"]) && $searchCriteria["sprint"]  > 0 && $searchCriteria["sprint"]  != "all") {
                foreach (explode(",", $searchCriteria["sprint"]) as $key => $sprint) {
                    $stmn->bindValue(":sprint" . $key, $sprint, PDO::PARAM_STR);
                }
            }

            $stmn->execute();
            $values = $stmn->fetchAll(PDO::FETCH_CLASS, 'Leantime\Domain\Tickets\Models\Tickets');
            $stmn->closeCursor();

            return $values;
        }

        /**
         * getType - get the Type from the type array
         *
         * @access public
         * @return array
         */
        public function getType(): array
        {
            return $this->type;
        }

        /**
         * getPriority - get the priority from the priority array
         *
         * @access public
         * @param  $priority
         * @return string
         */
        public function getPriority($priority): string
        {

            if ($priority !== null && $priority !== '') {
                return $this->priority[$priority];
            } else {
                return $this->priority[1];
            }
        }

        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        public function getFirstTicket($projectId): mixed
        {

            $query = "SELECT
						zp_tickets.id,
						zp_tickets.headline,
						zp_tickets.type,
						zp_tickets.description,
						zp_tickets.date,
						DATE_FORMAT(zp_tickets.date, '%Y,%m,%e') AS timelineDate,
						DATE_FORMAT(zp_tickets.dateToFinish, '%Y,%m,%e') AS timelineDateToFinish,
						zp_tickets.dateToFinish,
						zp_tickets.projectId,
						zp_tickets.priority,
						zp_tickets.status,
						zp_tickets.sprint,
						zp_tickets.storypoints,
						zp_tickets.hourRemaining,
						zp_tickets.acceptanceCriteria,
						zp_tickets.userId,
						zp_tickets.editorId,
						zp_tickets.planHours,
						zp_tickets.tags,
						zp_tickets.url,
						zp_tickets.editFrom,
						zp_tickets.editTo,
						zp_tickets.dependingTicketId,
						zp_tickets.milestoneid

					FROM
						zp_tickets
					WHERE
						zp_tickets.type <> 'milestone' AND zp_tickets.projectId = :projectId
                    ORDER BY
					    zp_tickets.date ASC
					LIMIT 1";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);

            $stmn->execute();
            $stmn->setFetchMode(PDO::FETCH_CLASS, 'Leantime\Domain\Tickets\Models\Tickets');
            $values = $stmn->fetch();
            $stmn->closeCursor();

            return $values;
        }

        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        public function getNumberOfAllTickets($projectId = null): mixed
        {

            $query = "SELECT
						COUNT(zp_tickets.id) AS allTickets
					FROM
						zp_tickets
					WHERE
						zp_tickets.type <> 'milestone'";

            if (!is_null($projectId)) {
                $query .= "AND zp_tickets.projectId = :projectId ";
            }

            $stmn = $this->db->database->prepare($query);

            if (!is_null($projectId)) {
                $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);
            }

            $stmn->execute();

            $values = $stmn->fetch();
            $stmn->closeCursor();

            return $values['allTickets'];
        }

        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        public function getNumberOfMilestones($projectId = null): mixed
        {

            $query = "SELECT
						COUNT(zp_tickets.id) AS allTickets
					FROM
						zp_tickets
					WHERE
						zp_tickets.type = 'milestone' ";

            if (!is_null($projectId)) {
                $query .= "AND zp_tickets.projectId = :projectId ";
            }

            $stmn = $this->db->database->prepare($query);

            if (!is_null($projectId)) {
                $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);
            }

            $stmn->execute();

            $values = $stmn->fetch();
            $stmn->closeCursor();

            return $values['allTickets'];
        }

        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        public function getNumberOfClosedTickets($projectId): mixed
        {

            $statusGroups = $this->getStatusListGroupedByType($projectId);

            $query = "SELECT
						COUNT(zp_tickets.id) AS allTickets
					FROM
						zp_tickets
					WHERE
						zp_tickets.type <> 'milestone' AND zp_tickets.projectId = :projectId
						AND zp_tickets.status " . $statusGroups["DONE"] . "
                    ORDER BY
					    zp_tickets.date ASC
					LIMIT 1";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);

            $stmn->execute();

            $values = $stmn->fetch();
            $stmn->closeCursor();

            return $values['allTickets'];
        }

        /**
         * @param $projectId
         * @param $averageStorySize
         * @return mixed
         */
        /**
         * @param $projectId
         * @param $averageStorySize
         * @return mixed
         */
        /**
         * @param $projectId
         * @param $averageStorySize
         * @return mixed
         */
        /**
         * @param $projectId
         * @param $averageStorySize
         * @return mixed
         */
        public function getEffortOfClosedTickets($projectId, $averageStorySize): mixed
        {

            $statusGroups = $this->getStatusListGroupedByType($projectId);

            $query = "SELECT
						SUM(CASE when zp_tickets.storypoints <> '' then zp_tickets.storypoints else :avgStorySize end) AS allEffort
					FROM
						zp_tickets
					WHERE
						zp_tickets.type <> 'milestone' AND zp_tickets.projectId = :projectId
						AND zp_tickets.status " . $statusGroups["DONE"] . "
                    ORDER BY
					    zp_tickets.date ASC
					LIMIT 1";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);
            $stmn->bindValue(':avgStorySize', $averageStorySize, PDO::PARAM_INT);


            $stmn->execute();

            $values = $stmn->fetch();
            $stmn->closeCursor();

            return $values['allEffort'];
        }

        /**
         * @param $projectId
         * @param $averageStorySize
         * @return mixed
         */
        /**
         * @param $projectId
         * @param $averageStorySize
         * @return mixed
         */
        /**
         * @param $projectId
         * @param $averageStorySize
         * @return mixed
         */
        /**
         * @param $projectId
         * @param $averageStorySize
         * @return mixed
         */
        public function getEffortOfAllTickets($projectId, $averageStorySize): mixed
        {

            $query = "SELECT
						SUM(CASE when zp_tickets.storypoints <> '' then zp_tickets.storypoints else :avgStorySize end) AS allEffort
					FROM
						zp_tickets
					WHERE
						zp_tickets.type <> 'milestone' AND zp_tickets.projectId = :projectId
                    ORDER BY
					    zp_tickets.date ASC
					LIMIT 1";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);
            $stmn->bindValue(':avgStorySize', $averageStorySize, PDO::PARAM_INT);

            $stmn->execute();

            $values = $stmn->fetch();
            $stmn->closeCursor();

            return $values['allEffort'];
        }

        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        /**
         * @param $projectId
         * @return mixed
         */
        public function getAverageTodoSize($projectId): mixed
        {
            $query = "SELECT
						AVG(zp_tickets.storypoints) as avgSize
					FROM
						zp_tickets
					WHERE
						zp_tickets.type <> 'milestone' AND
						(zp_tickets.storypoints <> '' AND zp_tickets.storypoints IS NOT NULL) AND zp_tickets.projectId = :projectId
                    ORDER BY
					    zp_tickets.date ASC
					LIMIT 1";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);

            $stmn->execute();

            $values = $stmn->fetch();
            $stmn->closeCursor();

            return $values['avgSize'];
        }

        /**
         * addTicket - add a Ticket with postback test
         *
         * @access public
         * @param array $values
         * @return bool|int
         */
        public function addTicket(array $values): bool|int
        {


            $query = "INSERT INTO zp_tickets (
						headline,
						type,
						description,
						date,
						dateToFinish,
						projectId,
						status,
						userId,
						tags,
						sprint,
						storypoints,
						priority,
						hourRemaining,
						planHours,
						acceptanceCriteria,
						editFrom,
						editTo,
						editorId,
						dependingTicketId,
                        milestoneid,
						sortindex,
						kanbanSortindex
				) VALUES (
						:headline,
						:type,
						:description,
						:date,
						:dateToFinish,
						:projectId,
						:status,
						:userId,
						:tags,
						:sprint,
						:storypoints,
						:priority,
						:hourRemaining,
						:planHours,
						:acceptanceCriteria,
						:editFrom,
						:editTo,
						:editorId,
						:dependingTicketId,
				         :milestoneid,
						0,
						0
				)";

            $stmn = $this->db->database->prepare($query);

            $stmn->bindValue(':headline', $values['headline'], PDO::PARAM_STR);
            $stmn->bindValue(':type', $values['type'], PDO::PARAM_STR);
            $stmn->bindValue(':description', $values['description'], PDO::PARAM_STR);
            $stmn->bindValue(':date', $values['date'], PDO::PARAM_STR);
            $stmn->bindValue(':dateToFinish', $values['dateToFinish'], PDO::PARAM_STR);
            $stmn->bindValue(':projectId', $values['projectId'], PDO::PARAM_STR);
            $stmn->bindValue(':status', $values['status'], PDO::PARAM_STR);
            $stmn->bindValue(':userId', $values['userId'], PDO::PARAM_STR);
            $stmn->bindValue(':tags', $values['tags'], PDO::PARAM_STR);

            $stmn->bindValue(':sprint', $values['sprint'], PDO::PARAM_STR);
            $stmn->bindValue(':storypoints', $values['storypoints'], PDO::PARAM_STR);
            $stmn->bindValue(':priority', $values['priority'], PDO::PARAM_STR);
            $stmn->bindValue(':hourRemaining', $values['hourRemaining'], PDO::PARAM_STR);
            $stmn->bindValue(':planHours', $values['planHours'], PDO::PARAM_STR);
            $stmn->bindValue(':acceptanceCriteria', $values['acceptanceCriteria'], PDO::PARAM_STR);

            $stmn->bindValue(':editFrom', $values['editFrom'], PDO::PARAM_STR);
            $stmn->bindValue(':editTo', $values['editTo'], PDO::PARAM_STR);
            $stmn->bindValue(':editorId', $values['editorId'], PDO::PARAM_STR);

            $depending = $values['dependingTicketId'] ?? "";

            $stmn->bindValue(':dependingTicketId', $depending, PDO::PARAM_STR);

            $milestoneId = $values['milestoneid'] ?? "";

            $stmn->bindValue(':milestoneid', $milestoneId, PDO::PARAM_STR);

            $stmn->execute();

            $stmn->closeCursor();

            if ($this->db->database->lastInsertId() !== false) {
                return intval($this->db->database->lastInsertId());
            }

            return false;
        }


        /**
         * @param $id
         * @param $params
         * @return bool
         */
        /**
         * @param $id
         * @param $params
         * @return bool
         */
        /**
         * @param $id
         * @param $params
         * @return bool
         */
        /**
         * @param $id
         * @param $params
         * @return bool
         */
        public function patchTicket($id, $params): bool
        {

            $this->addTicketChange($_SESSION['userdata']['id'], $id, $params);

            $sql = "UPDATE zp_tickets SET ";

            foreach ($params as $key => $value) {
                $sql .= "" . DbCore::sanitizeToColumnString($key) . "=:" . DbCore::sanitizeToColumnString($key) . ", ";
                //send status update event
                if ($key == 'status') {
                    static::dispatch_event("ticketStatusUpdate", array("ticketId" => $id, "status" => $value, "action" => "ticketStatusUpdate"));
                }
            }

            $sql .= "id=:id WHERE id=:id LIMIT 1";

            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':id', $id, PDO::PARAM_STR);

            foreach ($params as $key => $value) {
                $stmn->bindValue(':' . DbCore::sanitizeToColumnString($key), $value, PDO::PARAM_STR);
            }

            $return = $stmn->execute();
            $stmn->closeCursor();


            return $return;
        }

        /**
         * updateTicket - Update Ticketinformation
         *
         * @access public
         * @param array $values
         * @param  $id
         * @return bool
         */
        public function updateTicket(array $values, $id): bool
        {

            $this->addTicketChange($_SESSION['userdata']['id'], $id, $values);

            $query = "UPDATE zp_tickets
			SET
				headline = :headline,
				type = :type,
				description=:description,
				projectId=:projectId,
				status = :status,
				dateToFinish = :dateToFinish,
				sprint = :sprint,
				storypoints = :storypoints,
				priority = :priority,
				hourRemaining = :hourRemaining,
				planHours = :planHours,
				tags = :tags,
				editorId = :editorId,
				editFrom = :editFrom,
				editTo = :editTo,
				acceptanceCriteria = :acceptanceCriteria,
				dependingTicketId = :dependingTicketId,
				milestoneid = :milestoneid
			WHERE id = :id LIMIT 1";

            $stmn = $this->db->database->prepare($query);

            $stmn->bindValue(':headline', $values['headline'], PDO::PARAM_STR);
            $stmn->bindValue(':type', $values['type'], PDO::PARAM_STR);
            $stmn->bindValue(':description', $values['description'], PDO::PARAM_STR);
            $stmn->bindValue(':projectId', $values['projectId'], PDO::PARAM_STR);
            $stmn->bindValue(':status', $values['status'], PDO::PARAM_STR);
            $stmn->bindValue(':dateToFinish', $values['dateToFinish'], PDO::PARAM_STR);
            $stmn->bindValue(':sprint', $values['sprint'], PDO::PARAM_STR);
            $stmn->bindValue(':storypoints', $values['storypoints'], PDO::PARAM_STR);
            $stmn->bindValue(':priority', $values['priority'], PDO::PARAM_STR);
            $stmn->bindValue(':hourRemaining', $values['hourRemaining'], PDO::PARAM_STR);
            $stmn->bindValue(':acceptanceCriteria', $values['acceptanceCriteria'], PDO::PARAM_STR);
            $stmn->bindValue(':planHours', $values['planHours'], PDO::PARAM_STR);
            $stmn->bindValue(':tags', $values['tags'], PDO::PARAM_STR);
            $stmn->bindValue(':editorId', $values['editorId'], PDO::PARAM_STR);
            $stmn->bindValue(':editFrom', $values['editFrom'], PDO::PARAM_STR);
            $stmn->bindValue(':editTo', $values['editTo'], PDO::PARAM_STR);
            $stmn->bindValue(':id', $id, PDO::PARAM_STR);
            $stmn->bindValue(':dependingTicketId', $values['dependingTicketId'], PDO::PARAM_STR);
            $stmn->bindValue(':milestoneid', $values['milestoneid'], PDO::PARAM_STR);


            $result = $stmn->execute();

            $stmn->closeCursor();

            return $result;
        }

        /**
         * @param $ticketId
         * @param $status
         * @param $ticketSorting
         * @param $handler
         * @return bool
         */
        /**
         * @param $ticketId
         * @param $status
         * @param $ticketSorting
         * @param $handler
         * @return bool
         */
        /**
         * @param $ticketId
         * @param $status
         * @param $ticketSorting
         * @param $handler
         * @return bool
         */
        /**
         * @param $ticketId
         * @param $status
         * @param int      $ticketSorting
         * @param $handler
         * @return bool
         */
        public function updateTicketStatus($ticketId, $status, int $ticketSorting = -1, $handler = null): bool
        {

            $this->addTicketChange($_SESSION['userdata']['id'], $ticketId, array('status' => $status));

            if ($ticketSorting > -1) {
                $query = "UPDATE zp_tickets
					SET
						kanbanSortIndex = :sortIndex,
						status = :status
					WHERE id = :ticketId
					LIMIT 1";


                $stmn = $this->db->database->prepare($query);
                $stmn->bindValue(':status', $status, PDO::PARAM_INT);
                $stmn->bindValue(':sortIndex', $ticketSorting, PDO::PARAM_INT);
                $stmn->bindValue(':ticketId', $ticketId, PDO::PARAM_INT);
            } else {
                $query = "UPDATE zp_tickets
					SET
						status = :status
					WHERE id = :ticketId
					LIMIT 1";


                $stmn = $this->db->database->prepare($query);
                $stmn->bindValue(':status', $status, PDO::PARAM_INT);
                $stmn->bindValue(':ticketId', $ticketId, PDO::PARAM_INT);
            }

            static::dispatch_event("ticketStatusUpdate", array("ticketId" => $ticketId, "status" => $status, "action" => "ticketStatusUpdate", "handler" => $handler));
            return $stmn->execute();



            $stmn->closeCursor();
        }

        /**
         * @param $userId
         * @param $ticketId
         * @param $values
         * @return void
         */
        /**
         * @param $userId
         * @param $ticketId
         * @param $values
         * @return void
         */
        /**
         * @param $userId
         * @param $ticketId
         * @param $values
         * @return void
         */
        /**
         * @param $userId
         * @param $ticketId
         * @param $values
         * @return void
         */
        public function addTicketChange($userId, $ticketId, $values): void
        {
            if (empty($ticketId)) {
                return;
            }

            $fields = array(
                'headline' => 'headline',
                'type' => 'type',
                'description' => 'description',
                'project' => 'projectId',
                'priority' => 'priority',
                'deadline' => 'dateToFinish',
                'editors' => 'editorId',
                'fromDate' => 'editFrom',
                'toDate' => 'editTo',
                'staging' => 'staging',
                'production' => 'production',
                'planHours'    => 'planHours',
                'status' => 'status',
            );

            $changedFields = array();

            $sql = "SELECT * FROM zp_tickets WHERE id=:ticketId LIMIT 1";

            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':ticketId', $ticketId, PDO::PARAM_INT);

            $stmn->execute();
            $oldValues = $stmn->fetch();
            $stmn->closeCursor();


            // compare table
            foreach ($fields as $enum => $dbTable) {
                if (
                    isset($values[$dbTable]) === true &&
                    isset($oldValues[$dbTable]) === true &&

                    ($oldValues[$dbTable] != $values[$dbTable]) &&
                    ($values[$dbTable] != "")
                ) {
                    $changedFields[$enum] = $values[$dbTable];
                }
            }

            $sql = "INSERT INTO zp_tickethistory (
					userId, ticketId, changeType, changeValue, dateModified
				) VALUES (
					:userId, :ticketId, :changeType, :changeValue, :date
				)";

            $stmn = $this->db->database->prepare($sql);

            foreach ($changedFields as $field => $value) {
                $stmn->bindValue(':userId', $userId, PDO::PARAM_INT);
                $stmn->bindValue(':ticketId', $ticketId, PDO::PARAM_INT);
                $stmn->bindValue(':changeType', $field, PDO::PARAM_STR);
                $stmn->bindValue(':changeValue', $value, PDO::PARAM_STR);
                $stmn->bindValue(':date', date("Y-m-d H:i:s"), PDO::PARAM_STR);

                $stmn->execute();
            }

            $stmn->closeCursor();
        }

        /**
         * delTicket - delete a Ticket and all dependencies
         *
         * @access public
         * @param  $id
         * @return bool
         */
        public function delticket($id): bool
        {

            $query = "DELETE FROM zp_tickets WHERE id = :id";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':id', $id, PDO::PARAM_STR);
            $result = $stmn->execute();
            $stmn->closeCursor();

            return $result;
        }

        /**
         * @param $id
         * @return true
         */
        /**
         * @param $id
         * @return true
         */
        /**
         * @param $id
         * @return true
         */
        /**
         * @param $id
         * @return true
         */
        public function delMilestone($id): true
        {

            $query = "UPDATE zp_tickets
                SET
                    milestoneid = ''
                WHERE milestoneid = :id";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':id', $id, PDO::PARAM_STR);
            $stmn->execute();


            $query = "UPDATE zp_canvas_items
                SET
                    milestoneid = ''
                WHERE milestoneid = :id";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':id', $id, PDO::PARAM_STR);
            $stmn->execute();


            $query = "DELETE FROM zp_tickets WHERE id = :id";

            $stmn = $this->db->database->prepare($query);
            $stmn->bindValue(':id', $id, PDO::PARAM_STR);
            $stmn->execute();

            return true;
        }
    }

}
