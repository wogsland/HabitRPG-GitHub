<?php
/**
 * Function to process a new commit from the service hook.
 *
 * @param string $repoName - the name of the ropository committed to
 * @param string $user - the github username
 * @param int $count - the number of commits in the push
 * @param string $token - the user's unique token
 */
function newCommit ($repoName, $user, $count, $token) {
  global $db;
  require_once("connect.php");
  $query = "SELECT * FROM ".MYSQL_PREFIX."userInfo WHERE forUser=:forUser AND repoName=:repoName";
  $stmt = $db->prepare($query);
  $stmt->execute(array(':forUser' => $user, ':repoName' => $repoName));
  $row_count = $stmt->rowCount();
  echo "Found $row_count rows for $user and repo $repoName to record the $count commits\n";
  if ($row_count == 1) {
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $resultsArray = $results[0];
    $current = $resultsArray['current'];
    $totalHabit = $resultsArray['totalHabit'];
    $totalCommits = $resultsArray['totalCommits'];
    $direction = $resultsArray['direction'];
    $forEvery = $resultsArray['forEvery'];
    $id = $resultsArray['id'];

    // Get HabitRPG information on the user
    $stmt = $db->prepare("SELECT * FROM ".MYSQL_PREFIX."users WHERE username=:username AND token=:token");
    $stmt->execute(array(':username' => $user, ':token' => $token));
    $row_count = $stmt->rowCount();
    if ($row_count == 1) {
      $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $resultsArray2 = $results[0];
      $userId = $resultsArray2['userId'];
      $apiToken = $resultsArray2['apiToken'];
    } else {
      return; // no user exists
    }

    // calculate the HabitRPG changes
    $newCurrent = $count % $forEvery;
    $habitsForThis = round(($count / $forEvery), 0, PHP_ROUND_HALF_DOWN);
    $newTotalCommits = $totalCommits + $count;
    $newTotalHabit = $totalHabit + $habitsForThis;

    // make the changes in the database
    $stmt = $db->prepare("UPDATE ".MYSQL_PREFIX."userInfo SET current=?,totalHabit=?,totalCommits=? WHERE id=?");
    $stmt->execute(array($newCurrent, $newTotalHabit,$newTotalCommits,$id));
    $affected_rows = $stmt->rowCount();

    // make the changes in HabitRPG
    if ($affected_rows > 0) {
      // see if HabitRPG is up
      $test = new HabitRPGStatus($userId,$apiToken);
      echo "HabitRPG is ".($test->up()?'up':'not up')."\n";

      $huser = new HabitRPGUser($userId,$apiToken);
      //print_r($huser);

      // create task
      $text = 'GitHub push to '.$repoName.' '.rand();
      //$text = 'GitHub push '.rand();
      $type = 'todo';
      $note = 'HabitRPG-GitHub.  Sync your GitHub commits to gain XP!';
      $task = array('type'=>$type,
                    'text'=>$text
                    );
      $result = $huser->newTask($task);
      print_r($result);
      $taskId = $result['habitRPGData']['_id'];

      // score it
      if ($direction == 1) {
        $direction = "up";
      } else {
        $direction = "down";
      }
      $scoringParams = array('taskId'=>$taskId, 'direction'=>$direction);

      $i = 0;
      while ($i < $habitsForThis) {
        $score = $huser->taskScoring($scoringParams);
        print_r($score);
        $i++;
      }
      echo "HabitRPG updated";
    }
  } elseif ($row_count == 0) {
    //create a new row
  }
}
?>
