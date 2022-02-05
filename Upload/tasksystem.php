<?php
// error_reporting ( -1 );
// ini_set ( 'display_errors', true ); 
// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


function tasksystem_info()
{
  return array(
    "name" => "Aufgabensystem",
    "description" => "Aufgabensystem für Moderatoren",
    "website" => "https://github.com/katjalennartz",
    "author" => "risuena",
    "authorsite" => "https://github.com/katjalennartz",
    "version" => "1.0",
    "compatibility" => "18*"
  );
}

function tasksystem_is_installed()
{
  global $db;
  if ($db->table_exists("tasksystem")) {
    return true;
  }
  return false;
}

function tasksystem_install()
{
  global $db, $cache;
  //reste löschen wenn was schiefgegangen ist
  tasksystem_uninstall();

  // Erstellen der Tabellen
  // Die Typen und ihre Einstellungen
  $db->query("CREATE TABLE " . TABLE_PREFIX . "tasksystem (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `taskname` varchar(100) NOT NULL,
    `taskdescr` varchar(500) NOT NULL,
    `uid` varchar(100) NOT NULL,
    `startdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `enddate` DATETIME NOT NULL,
    `repetition` varchar(100) NOT NULL DEFAULT 'none',
    PRIMARY KEY (`id`)
     ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");

  // Einstellungen
  $setting_group = array(
    'name' => 'tasksystem',
    'title' => 'Aufgabensystem',
    'description' => 'Einstellungen für das Aufgabensystem für Moderatiren',
    'disporder' => 8, // The order your setting group will display
    'isdefault' => 0
  );
  $gid = $db->insert_query("settinggroups", $setting_group);
  $setting_array = array(
    'tasksystem_index' => array(
      'title' => 'Anzeige auf dem Index?',
      'description' => 'Sollen die Aufgaben auf dem Index angezeigt werden?',
      'optionscode' => 'yesno',
      'value' => '1',
      'disporder' => 1,
    ),
    'tasksystem_as' => array(
      'title' => 'Accountswitcher?',
      'description' => 'Wird der Accountswitcher verwendet?',
      'optionscode' => 'yesno',
      'value' => '1',
      'disporder' => 2,
    ),
    'tasksystem_erledigt' => array(
      'title' => 'Thread solved?',
      'description' => 'Wird zur markierung fertiger Steckbriefe das Threadsolved Plugin verwendet?',
      'value' => '1',
      'optionscode' => 'yesno',
      'disporder' => 3,
    ),
    'tasksystem_stecki' => array(
      'title' => 'Steckbriefe?',
      'description' => 'Sollen Steckbriefe automatisch in die Aufgaben eingetragen werden?',
      'optionscode' => 'yesno',
      'value' => '1',
      'disporder' => 4,
    ),
    'tasksystem_fid' => array(
      'title' => 'Steckbriefarea?',
      'description' => 'Wie ist die id eurer Steckbriefare? Mehrere mit Komma getrennt angeben.',
      'optionscode' => 'text',
      'disporder' => 5,
    ),
    'tasksystem_days' => array(
      'title' => 'Steckbriefkontrolle?',
      'description' => 'Wie viele Tage haben die Moderatoren Zeit um einen Steckbrief zu kontrollieren (Berechnung für Enddatum).',
      'optionscode' => 'numeric',
      'value' => '7',
      'disporder' => 6,
    ),
    'tasksystem_allowed' => array(
      'title' => 'Usergruppen?',
      'description' => 'Welche Usergruppen sollen Aufgaben nehmen und erstellen dürfen?',
      'optionscode' => 'groupselect',
      'value' => '4',
      'disporder' => 7,
    ),
  );

  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();


  //Templates erstellen
  $template[0] = array(
    "title" => 'tasksystem_index',
    "template" => '
    <div class="tasksystemcontainer">
      {$tasksystem_indexbit}
    </div>
    ',
    "sid" => "-1",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[1] = array(
    "title" => 'tasksystem_indexbit',
    "template" => '<div class="tasksystemcontainer__item task">
    <div class="task__item name">
		<b>{$task[\\\'taskname\\\']}</b> {$take}{$done}
    </div>
    <div class="task__item descr">
   		{$task[\\\'taskdescr\\\']}
    </div>
    <div class="task__item since">
    Offen seit: {$task[\\\'start\\\']}
    </div>
    <div class="task__item until">
    Bis: {$task[\\\'end\\\']}
    </div>
  </div>
  ',
    "sid" => "-1",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  foreach ($template as $row) {
    $db->insert_query("templates", $row);
  }

  $css = array(
    'name' => 'tasksystem.css',
    'tid' => 1,
    'attachedto' => '',
    "stylesheet" =>    '
    .tasksystemcontainer {
      display: flex;
      flex-wrap: wrap;
      background-color: grey;
      margin: 5px 0;
      padding: 10px;
      gap: 5px;
  }
  
  .tasksystemcontainer__item {
    flex: 1 0 30%;
    background: dimgrey;
    padding: 5px;
  }
  .tasksystemcontainer__item .reminder {
    color: #851919;
    font-weight: bold;
  }
    ',
    'cachefile' => $db->escape_string(str_replace('/', '', 'tasksystem.css')),
    'lastmodified' => time()
  );

  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

  $sid = $db->insert_query("themestylesheets", $css);
  $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);

  $tids = $db->simple_select("themes", "tid");
  while ($theme = $db->fetch_array($tids)) {
    update_theme_stylesheet_list($theme['tid']);
  }
}

function tasksystem_uninstall()
{
  global $db, $cache;
  // Tabelle löschen wenn existiert
  if ($db->table_exists("tasksystem")) {
    $db->drop_table("tasksystem");
  }

  //TEMPLATES LÖSCHEN 
  $db->delete_query("templates", "title LIKE 'tasksystem%'");

  //EINSTELLUNGEN LÖSCHEN
  $db->delete_query('settings', "name LIKE 'tasksystem_%'");
  $db->delete_query('settinggroups', "name = 'tasksystem'");
  rebuild_settings();

  //css löschen

  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
  $db->delete_query("themestylesheets", "name = 'tasksystem.css'");
  $query = $db->simple_select("themes", "tid");
  while ($theme = $db->fetch_array($query)) {
    update_theme_stylesheet_list($theme['tid']);
  }
}

function tasksystem_activate()
{
  //VARIABLEN IN TEMPLATES EINFÜGEN
  include MYBB_ROOT . "/inc/adminfunctions_templates.php";
  find_replace_templatesets("index", "#" . preg_quote('{$header}') . "#i", '{$header}{$tasksystem_index}');
  change_admin_permission('config', 'tasksystem', 1);
}

function tasksystem_deactivate()
{
  // VARIABLEN AUS TEMPLATES LÖSCHEN
  include MYBB_ROOT . "/inc/adminfunctions_templates.php";
  find_replace_templatesets("index", "#" . preg_quote('{$tasksystem_index}') . "#i", '');
}

/**
 * action handler fürs acp konfigurieren
 */
$plugins->add_hook("admin_config_action_handler", "tasksystem_admin_config_action_handler");
function tasksystem_admin_config_action_handler(&$actions)
{
  $actions['tasksystem'] = array('active' => 'tasksystem', 'file' => 'tasksystem');
}

/**
 * Berechtigungen im ACP
 */
$plugins->add_hook("admin_config_permissions", "tasksystem_admin_config_permissions");
function tasksystem_admin_config_permissions(&$admin_permissions)
{
  global $lang;

  $admin_permissions['tasksystem'] = "Darf Aufgabensystem verwalten?";

  return $admin_permissions;
}
/**
 * Menü einfügen
 */
$plugins->add_hook("admin_config_menu", "tasksystem_admin_config_menu");
function tasksystem_admin_config_menu(&$sub_menu)
{
  global $mybb, $lang;

  $sub_menu[] = [
    "id" => "tasksystem",
    "title" => "Aufgabensystem",
    "link" => "index.php?module=config-tasksystem"
  ];
}
/**
 * Verwaltung der Aufgaben im ACP
 * (Azeigen/Anlegen/editieren)
 */
$plugins->add_hook("admin_load", "tasksystem_admin_load");
function tasksystem_admin_load()
{
  global $mybb, $db, $lang, $page, $run_module, $action_file;


  if ($page->active_action != 'tasksystem') {
    return false;
  }
  //fun is starting
  if ($run_module == 'config' && $action_file == 'tasksystem') {
    //Settings holen
    $accountswitcher = $mybb->settings['tasksystem_as'];
    $groups  = $mybb->settings['tasksystem_allowed'];

    //Übersicht laden
    if ($mybb->input['action'] == "" || !isset($mybb->input['action'])) {
      //nice title and navigation
      $page->add_breadcrumb_item("Aufgabensystem Moderatoren");
      $page->output_header("Aufgabensystem Moderatoren");
      //Untermenüs erstellen 
      $sub_tabs['tasksystem'] = [
        "title" => "Übersicht",
        "link" => "index.php?module=config-tasksystem",
        "description" => "Übersicht der aktuellen Aufgaben"
      ];
      $sub_tabs['tasksystem_add'] = [
        "title" => "Aufgabe eintragen",
        "link" => "index.php?module=config-tasksystem&amp;action=create_task",
        "description" => "Hier kannst du eine neue Aufgabe eintragen."
      ];
      $page->output_nav_tabs($sub_tabs, 'tasksystem');

      //fehleranzeige
      if (isset($errors)) {
        $page->output_inline_error($errors);
      }

      //Übersicht: Container erstellen
      $form = new Form("index.php?module=config-tasksystem", "post");
      $form_container = new FormContainer("Aufgabensystem");
      $form_container->output_row_header("Übersicht");
      //Überschrift
      $form_container->output_row_header("<div style=\"text-align: center;\">Optionen</div>");

      //Alle Einträge aus taskstabelle bekommen um sie anzuzeigen, nach Enddatum sortiert
      $get_tasks = $db->simple_select("tasksystem", "*", "", ["order_by" => 'date_format(enddate, "%Y-%m-%d") ASC']);

      //Alle erstellen Aufgaben durchgehen.
      while ($task = $db->fetch_array($get_tasks)) {

        // ist die Aufgabe schon jemanden zugeteilt? 
        if (empty($task['uid'])) { //nö
          $username = "<span style=\"color: red;\">kein Verantwortlicher <b>-><a href=\"index.php?module=config-tasksystem&amp;action=tasksystem_take&amp;taskid={$task['id']}\">übernehmen</a></b></span>";
        } else { //jup
          $userids = explode(",", $task['uid']);
          $username = "";
          //get username
          foreach ($userids as $uid) {
            $user = get_user($uid);
            $username .= $user['username'] . ", ";
          }
        }
        //startdatum im schönen format erstellen
        $start = date("d.m.Y", strtotime($task['startdate']));
        //Enddatum im schönen format
        $end = date("d.m.Y", strtotime($task['enddate']));
        //abfangen wenn kein Enddatum angegeben ist 
        if ($end == '30.11.-0001') {
          $end = "kein Enddatum";
        }

        //menü für done, löschen & editieren
        $popup = new PopupMenu("tasksystem_{$task['id']}", "Optionen");
        //Je nach Repeat einstellung holen
        if ($task['repetition'] == "" || $task['repetition'] == "none") {
          $popup->add_item(
            "done & delete",
            "index.php?module=config-tasksystem&amp;action=tasksystem_delete&amp;taskid={$task['id']}"
              . "&amp;my_post_key={$mybb->post_code}"
          );
        }
        if ($task['repetition'] == "monthly") {
          $popup->add_item(
            "done & next month",
            "index.php?module=config-tasksystem&amp;action=tasksystem_nextmonth&amp;taskid={$task['id']}"
              . "&amp;my_post_key={$mybb->post_code}"
          );
        }
        if ($task['repetition'] == "weekly") {
          $popup->add_item(
            "done & next week",
            "index.php?module=config-tasksystem&amp;action=tasksystem_nextweek&amp;taskid={$task['id']}"
              . "&amp;my_post_key={$mybb->post_code}"
          );
        }
        $popup->add_item(
          "edit",
          "index.php?module=config-tasksystem&amp;action=tasksystem_edit&amp;taskid={$task['id']}"
        );
        $popup->add_item(
          "delete",
          "index.php?module=config-tasksystem&amp;action=tasksystem_delete&amp;taskid={$task['id']}"
            . "&amp;my_post_key={$mybb->post_code}"
        );

        //Infos zusammenbauen
        $form_container->output_cell('<strong>' . htmlspecialchars_uni($task['taskname']) . '</strong>
        <br/>
        <div style="margin: 5px; padding:5px; border: 1px dashed lightgrey; max-height:100px; overflow: auto;">' . $task['taskdescr'] . '</div>
        <i>' . $username . '</i> <br />
        <b>Start:</b> ' . $start . ' - <b>Ende:</b> ' . $end . '
        ');
        $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
        $form_container->construct_row();
      }
      $form_container->end();
      $form->end();
      $page->output_footer();

      die();
    } //Übersicht Ende

    // Eine Aufgabe erstellen
    if ($mybb->input['action'] == "create_task") {
      // Handler Task eintragen
      if ($mybb->request_method == "post") {
        //Fehler abfangen und anzeigen:
        if (empty($mybb->input['taskname'])) {
          $errors[] = "Fehler: Tasknamen wählen";
        }
        if (empty($mybb->input['taskdescr'])) {
          $errors[] = "Fehler: Taskbeschreibung erstellen";
        }
        if (empty($mybb->input['endate'])) {
          $errors[] = "Fehler: Enddatum wählen";
        }
        //Startdatum heute oder ein bestimmtes? 
        if (empty($mybb->input['startdate'])) {
          $today = new DateTime(date("Y-m-d", time())); //heute
          $start = $today->format('Y-m-d');
        } else {
          $start = $db->escape_string($mybb->input['startdate']);
        }
        //einfügen
        $insert = [
          "taskname" => $db->escape_string($mybb->input['taskname']),
          "taskdescr" => $db->escape_string($mybb->input['taskdescr']),
          "uid" => implode(",", $mybb->input['users']),
          "startdate" => $start,
          "enddate" => $mybb->input['enddate'],
          "repetition" =>  implode("", $mybb->input['repeat']),
        ];
        $db->insert_query("tasksystem", $insert);

        //log erstellen
        $mybb->input['module'] = "tasksystem";
        $mybb->input['action'] = "Erfolgreich gespeichert";
        log_admin_action("User: " . htmlspecialchars_uni(implode(",", $mybb->input['user'])) . " Aufgabe:" . htmlspecialchars_uni(implode(",", $mybb->input['taskname'])));
        flash_message("Erfolgreich gespeichert", 'success');
        admin_redirect("index.php?module=config-tasksystem");
      }

      $page->add_breadcrumb_item("Aufgabensystem");
      // Navigation oben erstellen
      $page->output_header("Aufgabensystem");

      //Untermenüs erstellen 
      $sub_tabs['tasksystem'] = [
        "title" => "Übersicht",
        "link" => "index.php?module=config-tasksystem",
        "description" => "Übersicht der aktuellen Aufgaben"
      ];
      $sub_tabs['tasksystem_add'] = [
        "title" => "Aufgabe eintragen",
        "link" => "index.php?module=config-tasksystem&amp;action=create_task",
        "description" => "Hier kannst du eine neue Aufgabe eintragen."
      ];

      $page->output_nav_tabs($sub_tabs, 'tasksystem_add');
      // Fehler Zeigen, wenn es welche gibt
      if (isset($errors)) {
        $page->output_inline_error($errors);
      }
      //formular und felder erstellen für Admin cp 
      $form = new Form("index.php?module=config-tasksystem&amp;action=create_task", "post", "", 1);
      $form_container = new FormContainer("Aufgaben erstellen");
      //textbox für name des tasks
      $form_container->output_row(
        "Name: <em>*</em>", //name
        "Einen bezeichnenden Namen der Aufgabe, der eventuell auch zur Darstellung/Ausgabe dient",
        $form->generate_text_box('taskname', $mybb->input['taskname'])
      );
      //textarea für Beschreibung
      $form_container->output_row(
        "Beschreibung: <em>*</em>",
        "Eine Beschreibung der Aufgabe.",
        $form->generate_text_area('taskdescr', $mybb->input['taskdescr'])
      );

      //Einmal User aus den angegebenen Gruppen holen und in ein array speichern.
      $allusers = array();
      //Accountswitcher? Dann nur Hauptaccounts holen
      if ($accountswitcher == 1) {
        $as = " AND as_uid = 0";
      } else {
        $as = "";
      }
      $users = $db->write_query(
        "SELECT username, uid FROM " . TABLE_PREFIX . "users WHERE usergroup IN ({$groups}) 
      OR additionalgroups like '%{$groups}%'" . $as . " ORDER BY username"
      );
      //alles in ein array speichern
      while ($result = $db->fetch_array($users)) {
        $uid = $result['uid'];
        $allusers[$uid] = $result['username'];
      }
      //vorsichtshalber leere sachen und so rauswerfen
      asort($allusers);
      //Select mit Usern erstellen
      $form_container->output_row(
        "Moderator", //name
        "auch mehrfachauswahl möglich",
        $form->generate_select_box(
          'users[]',
          $allusers,
          '',
          array('id' => 'uid', 'multiple' => true, 'size' => 4)
        ),
        'users'
      );
      // Einstellungen für Wiederholung der Aufgabe
      $repeat = array(
        'none' => 'none',
        'weekly' => 'weekly',
        'monthly' => 'monthly'
      );
      //selecgt erstellenn
      $form_container->output_row(
        "Wiederholung",
        "Auswählen ob und in welchem Interval die Aufgabe wiederholt werden soll.",
        $form->generate_select_box(
          'repeat[]',
          $repeat,
          '',
          array('id' => 'id', 'size' => 3)
        ),
        'repeat'
      );
      //startdatum erstellen
      $form_container->output_row(
        "Startdatum:", //datum
        "Hier ein Datum eintragen, wenn es ein anderes als heute sein soll. Format: 2021-12-01 .",
        $form->generate_text_box('startdate', $mybb->input['startdate'])
      );
      //Enddatum
      $form_container->output_row(
        "Enddatum: <em>*</em>", //datum
        "Hier ein Enddatum eintragen. Format: 2021-12-01 .",
        $form->generate_text_box('enddate', $mybb->input['enddate'])
      );

      $form_container->end();
      $buttons[] = $form->generate_submit_button("Aufgabe anlegen");
      $form->output_submit_wrapper($buttons);
      $form->end();
      $page->output_footer();
      die();
    }
    //Eine Aufgabe von der Übersicht aus übernehmen
    if ($mybb->input['action'] == "tasksystem_take") {
      $update =            [
        "uid" => $mybb->user['uid'],
      ];
      $db->update_query("tasksystem", $update, "id={$mybb->input['taskid']}");
      admin_redirect("index.php?module=config-tasksystem");
    }

    //Eine Aufgabe editieren
    if ($mybb->input['action'] == "tasksystem_edit") {
      //handler zum ändern
      if ($mybb->request_method == "post") {
        if (empty($mybb->input['taskname'])) {
          $errors[] = "Fehler: Tasknamen wählen";
        }
        if (empty($mybb->input['taskdescr'])) {
          $errors[] = "Fehler: Taskbeschreibung erstellen";
        }
        if (empty($errors)) {
          //Startdatum heute oder ein bestimmtes? 
          if (empty($mybb->input['startdate'])) {
            $today = new DateTime(date("Y-m-d", time())); //heute
            $start = $today->format('Y-m-d');
          } else {
            $start = $db->escape_string($mybb->input['startdate']);
          }
          $aid = $mybb->get_input('aid', MyBB::INPUT_INT);

          //aktualisieren
          $update = [
            "taskname" => $db->escape_string($mybb->input['taskname']),
            "taskdescr" => $db->escape_string($mybb->input['taskdescr']),
            "uid" => implode(",", $mybb->input['users']),
            "startdate" => $start,
            "enddate" => date("Y-m-d", strtotime($mybb->input['enddate'])),
            "repetition" =>  implode("", $mybb->input['repeat']),
          ];
          $db->update_query("tasksystem", $update, "id={$aid}");
          //log erstellen
          $mybb->input['module'] = "tasksystem";
          $mybb->input['action'] = "Erfolgreich aktualisiert";
          log_admin_action("User: " . htmlspecialchars_uni(implode(",", $mybb->input['user'])) . " Aufgabe:" . htmlspecialchars_uni(implode(",", $mybb->input['taskname'])));
          flash_message("Erfolgreich aktualisiert", 'success');
          admin_redirect("index.php?module=config-tasksystem");
        }
      }

      // Editieren
      //Fehleranzeige
      if (isset($errors)) {
        $page->output_inline_error($errors);
      }
      //Welche Aufgabe soll bearbeitet werden -> Infos bekommen
      $aid = $mybb->get_input('taskid', MyBB::INPUT_INT);
      $task = $db->simple_select("tasksystem", "*", "id={$aid}");
      $edit = $db->fetch_array($task);


      //Editieren Seite erstellen
      $page->add_breadcrumb_item("Aufgabensystem - Eintrag editieren");
      // Navigation oben erstellen
      $page->output_header("Aufgabensystem - Eintrag editieren");
      //Untermenüs erstellen 
      $sub_tabs['tasksystem'] = [
        "title" => "Übersicht",
        "link" => "index.php?module=config-tasksystem",
        "description" => "Übersicht der aktuellen Aufgaben"
      ];
      $sub_tabs['tasksystem_add'] = [
        "title" => "Aufgabe eintragen",
        "link" => "index.php?module=config-tasksystem&amp;action=create_task",
        "description" => "Hier kannst du eine neue Aufgabe eintragen."
      ];
      $sub_tabs['tasksystem_edit'] = [
        "title" => "Aufgabe editieren",
        "description" => "Du editierst folgenden Eintrag: <b>{$edit['taskname']}</b>"
      ];
      $page->output_nav_tabs($sub_tabs, 'tasksystem_edit');

      $form = new Form("index.php?module=config-tasksystem&amp;action=tasksystem_edit", "post", "", 1);
      $form_container = new FormContainer("Eintrag editieren");
      $form_container->output_row(
        "Name: <em>*</em>", //name
        "Einen bezeichnenden Namen der Aufgabe, der eventuell auch zur Darstellung/Ausgabe dient",
        $form->generate_text_box('taskname',  htmlspecialchars_uni($edit['taskname']))
      );
      echo $form->generate_hidden_field('aid', $aid);

      //textarea für Beschreibung
      $form_container->output_row(
        "Beschreibung: <em>*</em>",
        "Eine Beschreibung der Aufgabe.",
        $form->generate_text_area('taskdescr', htmlspecialchars_uni($edit['taskdescr']))
      );

      //Einmal User aus den angegebenen Gruppen holen und in ein array speichern.
      $allusers = array();
      //Accountswitcher? Dann nur Hauptaccounts holen
      if ($accountswitcher == 1) {
        $as = " AND as_uid = 0";
      } else {
        $as = "";
      }
      $users = $db->write_query(
        "SELECT username, uid FROM " . TABLE_PREFIX . "users WHERE usergroup IN ({$groups}) 
            OR additionalgroups like '%{$groups}%'" . $as . " ORDER BY username"
      );
      //alles in ein array speichern
      while ($result = $db->fetch_array($users)) {
        $uid = $result['uid'];
        $allusers[$uid] = $result['username'];
      }
      //vorsichtshalber leere sachen und so rauswerfen
      asort($allusers);
      //Select mit Usern erstellen
      $form_container->output_row(
        "Moderator", //name
        "auch mehrfachauswahl möglich",
        $form->generate_select_box(
          'users[]',
          $allusers,
          $edit['uid'],
          array('id' => 'uid', 'multiple' => true, 'size' => 4)
        ),
        'users'
      );
      // Einstellungen für Wiederholung der Aufgabe
      $repeat = array(
        'none' => 'none',
        'weekly' => 'weekly',
        'monthly' => 'monthly'
      );
      //select erstellenn
      $form_container->output_row(
        "Wiederholung",
        "Auswählen ob und in welchem Interval die Aufgabe wiederholt werden soll.",
        $form->generate_select_box(
          'repeat[]',
          $repeat,
          $edit['repetition'],
          array('id' => 'id', 'size' => 3)
        ),
        'repeat'
      );
      //startdatum erstellen
      $form_container->output_row(
        "Startdatum:", //datum
        "Hier ein Datum eintragen, wenn es ein anderes als heute sein soll. Format: 2021-12-01 .",
        $form->generate_text_box('startdate', date("Y-m-d", strtotime($edit['startdate'])))
      );
      //Enddatum
      $form_container->output_row(
        "Enddatum: <em>*</em>", //datum
        "Hier ein Enddatum eintragen. Format: 2021-12-01 .",
        $form->generate_text_box('enddate', date("Y-m-d", strtotime($edit['enddate'])))
      );

      $form_container->end();
      $buttons[] = $form->generate_submit_button("Aufgabe bearbeiten");
      $form->output_submit_wrapper($buttons);
      $form->end();
      $page->output_footer();
      die();
    }

    // Aufgabe endgültig Löschen 
    if ($mybb->input['action'] == "tasksystem_delete") {
      $aid = $mybb->get_input('taskid', MyBB::INPUT_INT);
      if (empty($aid)) {
        flash_message("Fehler beim Löschen", 'error');
        admin_redirect("index.php?module=config-tasksystem&amp;action=tasksystem_delete");
      }
      if (isset($mybb->input['no']) && $mybb->input['no']) {
        flash_message("Fehler beim Löschen", 'error');
        admin_redirect("index.php?module=config-tasksystem&amp;action=tasksystem_delete");
      }
      if (!verify_post_check($mybb->input['my_post_key'])) {
        flash_message("Fehler beim Authentifizieren mypostcode shit", 'error');
        admin_redirect("index.php?module=config-tasksystem&amp;action=tasksystem_delete");
      } else {
        if ($mybb->request_method == "post") {
          $db->delete_query("tasksystem", "id = {$aid}");
          $mybb->input['module'] = "tasksystem";
          $mybb->input['action'] = "Erfolgreich gelöscht";
          log_admin_action("User: " . htmlspecialchars_uni(implode(",", $mybb->user['username'])) . " Aufgabe:" . htmlspecialchars_uni(implode(",", $mybb->input['taskname'])));
          flash_message("Erfolgreich gelöscht", 'success');
          admin_redirect("index.php?module=config-tasksystem");
        } else {
          $page->output_confirm_action(
            "index.php?module=config-tasksystem&amp;action=tasksystem_delete&amp;taskid={$aid}",
            "Möchten du diese Aufgabe wirklich endgültig löschen?"
          );
        }
      }
    }

    // Aufgabe getan + Wiederholung 
    // 1 Month
    if ($mybb->input['action'] == "tasksystem_nextmonth") {
      $aid = $mybb->get_input('taskid', MyBB::INPUT_INT);
      $task = $db->simple_select("tasksystem", "*", "id={$aid}");
      $shift = $db->fetch_array($task);

      if (empty($aid)) {
        flash_message("Fehler beim verschieben", 'error');
        admin_redirect("index.php?module=config-tasksystem&amp;action=tasksystem_nextmonth");
      }
      if (isset($mybb->input['no']) && $mybb->input['no']) {
        flash_message("Fehler beim Löschen", 'error');
        admin_redirect("index.php?module=config-tasksystem&amp;action=tasksystem_nextmonth");
      }
      if (!verify_post_check($mybb->input['my_post_key'])) {
        flash_message("Fehler beim Authentifizieren mypostcode shit", 'error');
        admin_redirect("index.php?module=config-tasksystem&amp;action=tasksystem_nextmonth");
      } else {
        if ($mybb->request_method == "post") {
          $start = new DateTime(date("Y-m-d", strtotime($shift['startdate'])));
          $end = new DateTime(date("Y-m-d", strtotime($shift['enddate'])));
          $start = new DateTime(date("Y-m-d", strtotime($shift['startdate'])));
          $start = date_add($start, date_interval_create_from_date_string('1 Month'));
          $end = date_add($end, date_interval_create_from_date_string('1 Month'));

          $update = [
            "startdate" => $start->format('Y-m-d'),
            "enddate" => $end->format('Y-m-d'),
          ];

          $db->update_query("tasksystem", $update, "id = {$aid}");
          $mybb->input['module'] = "tasksystem";
          $mybb->input['action'] = "Erfolgreich verschoben";
          log_admin_action("User: " . htmlspecialchars_uni(implode(",", $mybb->user['username'])) . " Aufgabe:" . htmlspecialchars_uni(implode(",", $mybb->input['taskname'])));
          flash_message("Erfolgreich verschoben", 'success');
          admin_redirect("index.php?module=config-tasksystem");
        } else {
          $page->output_confirm_action(
            "index.php?module=config-tasksystem&amp;action=tasksystem_nextmonth&amp;taskid={$aid}",
            "Möchten du diese Aufgabe als erledigt markieren und das Start & Enddatum um einen Monat verschieben?"
          );
        }
      }
    }

    // 1 Week
    if ($mybb->input['action'] == "tasksystem_nextweek") {
      $aid = $mybb->get_input('taskid', MyBB::INPUT_INT);
      $task = $db->simple_select("tasksystem", "*", "id={$aid}");
      $shift = $db->fetch_array($task);

      if (empty($aid)) {
        flash_message("Fehler beim verschieben", 'error');
        admin_redirect("index.php?module=config-tasksystem&amp;action=tasksystem_nextmonth");
      }
      if (isset($mybb->input['no']) && $mybb->input['no']) {
        flash_message("Fehler beim Löschen", 'error');
        admin_redirect("index.php?module=config-tasksystem&amp;action=tasksystem_nextmonth");
      }
      if (!verify_post_check($mybb->input['my_post_key'])) {
        flash_message("Fehler beim Authentifizieren mypostcode shit", 'error');
        admin_redirect("index.php?module=config-tasksystem&amp;action=tasksystem_nextmonth");
      } else {
        if ($mybb->request_method == "post") {
          $start = new DateTime(date("Y-m-d", strtotime($shift['startdate'])));
          $end = new DateTime(date("Y-m-d", strtotime($shift['enddate'])));
          $start = new DateTime(date("Y-m-d", strtotime($shift['startdate'])));
          $start = date_add($start, date_interval_create_from_date_string('1 Week'));
          $end = date_add($end, date_interval_create_from_date_string('1 Week'));

          $update = [
            "startdate" => $start->format('Y-m-d'),
            "enddate" => $end->format('Y-m-d'),
          ];

          $db->update_query("tasksystem", $update, "id = {$aid}");
          $mybb->input['module'] = "tasksystem";
          $mybb->input['action'] = "Erfolgreich verschoben";
          log_admin_action("User: " . htmlspecialchars_uni(implode(",", $mybb->user['username'])) . " Aufgabe:" . htmlspecialchars_uni(implode(",", $mybb->input['taskname'])));
          flash_message("Erfolgreich verschoben", 'success');
          admin_redirect("index.php?module=config-tasksystem");
        } else {
          $page->output_confirm_action(
            "index.php?module=config-tasksystem&amp;action=tasksystem_nextmonth&amp;taskid={$aid}",
            "Möchten du diese Aufgabe als erledigt markieren und das Start & Enddatum um eine Woche verschieben?"
          );
        }
      }
    }
  }
}

/**** 
 * 
 * Das Plugin 'Thread erledigt' wird genutzt und verwendet um zu markieren, dass
 *  Steckbriefe fertig sind
 * 
 *****/
$plugins->add_hook("showthread_linear", "tasksystem_threadsolved", 5);
$plugins->add_hook("showthread_threaded", "tasksystem_threadsolved", 5);
function tasksystem_threadsolved()
{
  global $threadsolved, $thread, $mybb, $fid, $db, $tid;

  //unsere Einstellungen aus dem acp holen
  $fids = $mybb->settings['tasksystem_fid'];
  $days = $mybb->settings['tasksystem_days'];
  $solved = $mybb->settings['tasksystem_erledigt'];
  $stecki = $mybb->settings['tasksystem_stecki'];
  //wird überhaupt das threadsolved verwendet? 
  if ($solved == 1 && $stecki == 1) {
    //Prüfen ob wir uns in der Steckarea befinden
    if (stripos("," . $fids . ",", "," . $fid . ",") !== false) {
      //datum
      $today = new DateTime(date("Y-m-d", time()));
      $end = new DateTime(date("Y-m-d", time()));
      $end = date_add($end, date_interval_create_from_date_string($days . ' days'));
      // var_dump($end);
      // echo $end;
      // die();
      $steckilink = "<a href=\"showthread.php?tid={$tid}\">Steckbrief</a>";
      if ($mybb->input['marksolved'] == "1") {
        $insert = array(
          "taskname" => "Steckbriefkontrolle",
          "taskdescr" =>  $db->escape_string($steckilink) . " von " . $db->escape_string($thread['username']),
          "uid" => "",
          "startdate" => $today->format('Y-m-d'),
          "enddate" => $end->format('Y-m-d'),
        );
        $db->insert_query("tasksystem", $insert);
      }
    }
  }
}
/**** 
 * 
 * Das Plugin 'Thread erledigt' wird nicht genutzt
 * -> Steckbrief hinzufügen wenn Thread erstellt wird. 
 * 
 *****/
$plugins->add_hook("newthread_do_newthread_end", "tasksystem_do_newthread");
function tasksystem_do_newthread()
{
  global  $thread, $mybb, $fid, $db, $tid;
  //unsere Einstellungen aus dem acp holen
  $fids = $mybb->settings['tasksystem_fid'];
  $days = $mybb->settings['tasksystem_days'];
  $solved = $mybb->settings['tasksystem_erledigt'];
  $stecki = $mybb->settings['tasksystem_stecki'];

  //nur wenn thread solved nicht benutzt wird. Wir wollen ja keine doppelten Aufgaben :D 
  if ($solved == 0 && $stecki == 1) {
    //Prüfen ob wir uns in der Steckarea befinden
    if (stripos("," . $fids . ",", "," . $fid . ",") !== false) {
      //datum
      $today = new DateTime(date("Y-m-d", time()));
      $end = new DateTime(date("Y-m-d", time()));
      $end = date_add($end, date_interval_create_from_date_string($days . ' days'));

      $steckilink = "<a href=\"showthread.php?tid={$tid}\">Steckbrief</a>";
      $insert = array(
        "taskname" => "Steckbriefkontrolle",
        "taskdescr" =>  $db->escape_string($steckilink) . " von " . $db->escape_string($thread['username']),
        "uid" => "",
        "startdate" => $today->format('Y-m-d'),
        "enddate" => $end->format('Y-m-d'),
      );
      $db->insert_query("tasksystem", $insert);
    }
  }
}


/**
 * Darstellung im Forum (Ausgabe der Liste)
 */
$plugins->add_hook("index_start", "tasksystem_main");
function tasksystem_main()
{
  global $mybb, $db, $templates, $tasksystem_index, $tasksystem_indexbit;

  if ($mybb->settings['tasksystem_index'] == 1) {
    //wer ist online
    $thisuser = get_user($mybb->user['uid']);

    //einstellungen
    $groups = $mybb->settings['tasksystem_allowed'];
    $as = $mybb->settings['tasksystem_as'];
    $charas = array();
    if (is_member($groups, $thisuser['uid'])) {
      if ($as == 1) {
        $charas = tasksystem_get_allchars($thisuser['uid']);
      } else {
        $id = $thisuser['uid'];
        $charas[$id] = $thisuser['username'];
      }

      foreach ($charas as $uid => $chara) {
        // $tasksystem_indexbit="";
        $get_tasks = $db->write_query(
          "SELECT *, date_format(startdate, '%d.%m.%Y') as start, date_format(enddate, '%d.%m.%Y') as end FROM " . TABLE_PREFIX . "tasksystem 
             WHERE concat(',',uid,',') LIKE '%,{$uid},%'
             ORDER BY date_format(enddate, '%Y-%m-%d') ASC"
        );

        while ($task = $db->fetch_array($get_tasks)) {
          if ($task['uid'] == "") {
            $take = "<a href=\"index.php?action=tasksystem_take&amp;taskid={$task['id']}\">[take]</a>";
          } else {
            $take = " Deine Aufgabe ";
          }
          if ($task['repetition'] == 'none') {
            //done and delete
            $done = "<a href=\"index.php?action=tasksystem_delete&amp;taskid={$task['id']}\" onClick=\"return confirm('Möchtest du die Aufgabe wirklich endgültig löschen?');\">[done]</a>";
          } else if ($task['repetition'] == 'weekly') {
            //um eine woche verschieben
            $done = "<a href=\"index.php?action=tasksystem_nextweek&amp;taskid={$task['id']}\">[done]</a>";
          } else if ($task['repetition'] == 'monthly') {
            //um einen Monat verschieben
            $done = "<a href=\"index.php?action=tasksystem_nextmonth&amp;taskid={$task['id']}\">[done]</a>";
          }
          $today =  new DateTime(date("Y-m-d"));
          $end = new DateTime(date("Y-m-d", strtotime($task['enddate'])));
          $days = $today->diff($end);
          $computeddays = $days->format("%d");
          if ($computeddays <= 2) {
            $task['end'] = "<span class=\"reminder\">{$task['end']}</span>";
          }

          eval("\$tasksystem_indexbit .= \"" . $templates->get("tasksystem_indexbit") . "\";");
        }
      }
      $get_tasks_empty = $db->write_query(
        "SELECT *, date_format(startdate, '%d.%m.%Y') as start, date_format(enddate, '%d.%m.%Y') as end FROM " . TABLE_PREFIX . "tasksystem 
           WHERE uid = '' 
           ORDER BY date_format(enddate, '%Y-%m-%d') ASC"
      );
      while ($task = $db->fetch_array($get_tasks_empty)) {
        if ($task['uid'] == "") {
          $take = "<a href=\"index.php?action=tasksystem_take&amp;taskid={$task['id']}\">[take]</a>";
        } else {
          $take = " Deine Aufgabe ";
        }
        if ($task['repetition'] == 'none') {
          //done and delete
          $done = "<a href=\"index.php?action=tasksystem_delete&amp;taskid={$task['id']}\" onClick=\"return confirm('Möchtest du die Aufgabe wirklich endgültig löschen?');\">[done]</a>";
        } else if ($task['repetition'] == 'weekly') {
          //um eine woche verschieben
          $done = "<a href=\"index.php?action=tasksystem_nextweek&amp;taskid={$task['id']}\">[done]</a>";
        } else if ($task['repetition'] == 'monthly') {
          //um einen Monat verschieben
          $done = "<a href=\"index.php?action=tasksystem_nextmonth&amp;taskid={$task['id']}\">[done]</a>";
        }
        $today =  new DateTime(date("Y-m-d"));
        $end = new DateTime(date("Y-m-d", strtotime($task['enddate'])));
        $days = $today->diff($end);
        $computeddays = $days->format("%d");
        if ($computeddays <= 2) {
          $task['end'] = "<span class=\"reminder\">{$task['end']}</span>";
        }

        eval("\$tasksystem_indexbit .= \"" . $templates->get("tasksystem_indexbit") . "\";");
      }

      eval("\$tasksystem_index = \"" . $templates->get("tasksystem_index") . "\";");


      if ($mybb->input['action'] == 'tasksystem_take') {
        $aid = $mybb->get_input('taskid', MyBB::INPUT_INT);
        //wenn der as genutzt wird, wollen wir dass der hauptcharakter die aufgabe zugeteilt kriegt
        if ($as == 1) {
          foreach ($charas as $uid => $chara) {
            $userinfo = get_user($uid);
            if ($userinfo['as_uid'] == 0) {
              $haupt =  $uid;
            }
          }
        } else {
          //sonst der user der online ist
          $haupt = $mybb->user['uid'];
        }
        //einmal speichern
        $update = [
          "uid" => $haupt,
        ];
        $db->update_query("tasksystem", $update, "id={$aid}");
        redirect("index.php");
      }

      if ($mybb->input['action'] == 'tasksystem_nextweek') {
        $aid = $mybb->get_input('taskid', MyBB::INPUT_INT);
        //daten des Tasks bekommen
        $task = $db->simple_select("tasksystem", "*", "id={$aid}");
        $shift = $db->fetch_array($task);


        $start = new DateTime(date("Y-m-d", strtotime($shift['startdate'])));
        $end = new DateTime(date("Y-m-d", strtotime($shift['enddate'])));
        $start = new DateTime(date("Y-m-d", strtotime($shift['startdate'])));
        $start = date_add($start, date_interval_create_from_date_string('1 Week'));
        $end = date_add($end, date_interval_create_from_date_string('1 Week'));

        $update = [
          "startdate" => $start->format('Y-m-d'),
          "enddate" => $end->format('Y-m-d'),
        ];
        $db->update_query("tasksystem", $update, "id = {$aid}");
        redirect("index.php");
      }

      if ($mybb->input['action'] == 'tasksystem_nextmonth') {
        $aid = $mybb->get_input('taskid', MyBB::INPUT_INT);
        //daten des Tasks bekommen
        $task = $db->simple_select("tasksystem", "*", "id={$aid}");
        $shift = $db->fetch_array($task);


        $start = new DateTime(date("Y-m-d", strtotime($shift['startdate'])));
        $end = new DateTime(date("Y-m-d", strtotime($shift['enddate'])));
        $start = new DateTime(date("Y-m-d", strtotime($shift['startdate'])));
        $start = date_add($start, date_interval_create_from_date_string('1 Month'));
        $end = date_add($end, date_interval_create_from_date_string('1 Month'));

        $update = [
          "startdate" => $start->format('Y-m-d'),
          "enddate" => $end->format('Y-m-d'),
        ];
        $db->update_query("tasksystem", $update, "id = {$aid}");
        redirect("index.php");
      }

      if ($mybb->input['action'] == 'tasksystem_delete') {
        $aid = $mybb->get_input('taskid', MyBB::INPUT_INT);

        $db->delete_query("tasksystem", "id = {$aid}");
        //daten des Tasks bekommen
        redirect("index.php");
      }
    }
  }
}

/*#######################################
#Hilfsfunktion für Mehrfachcharaktere (accountswitcher)
#Alle angehangenen Charas holen
#an die Funktion übergeben: Wer ist Online, die dazugehörige accountswitcher ID (ID des Hauptcharas) 
######################################*/
function tasksystem_get_allchars($thisuser)
{
  global $mybb, $db;
  //wir brauchen die id des Hauptcharas
  $userinfo = get_user($thisuser);
  $as_uid = $userinfo['as_uid'];
  $charas = array();
  if ($as_uid == 0) {
    // as_uid = 0 wenn hauptaccount oder keiner angehangen
    $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $thisuser) OR (uid = $thisuser) ORDER BY username");
  } else if ($as_uid != 0) {
    //id des users holen wo alle an gehangen sind 
    $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $as_uid) OR (uid = $thisuser) OR (uid = $as_uid) ORDER BY username");
  }
  while ($users = $db->fetch_array($get_all_users)) {

    $uid = $users['uid'];
    $charas[$uid] = $users['username'];
  }
  return $charas;
}
