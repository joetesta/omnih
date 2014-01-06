<?php

# All in one Email page to:
# *) Enter Email content
# *) Enter Test addresses
# *) Schedule Emails to go out
# *) Hopefully set list criteria

# include our security and db connection:
require_once('src/MarketplaceWebServiceOrders/Samples/orders.db.inc');

# See if a form was posted

if( isset($_POST['sent_from']) ){

  # see which form was posted
  if( $_POST['sent_from']=='add_test_email' ){

    $new_email = $_POST['new_email'];
    $query = "INSERT INTO email_test_addr (`address`) VALUES ( ? )";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $new_email);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
    header('Location: email.php?page=test_email');

  } elseif( $_POST['sent_from']=='del_test_email' ){

    $del_email = $_POST['del_email'];
    $query = "DELETE FROM email_test_addr WHERE address = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $del_email);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
    header('Location: email.php?page=test_email');

  } elseif( $_POST['sent_from']=='schedule' ){

    $content_id = $_POST['content_id'];
    $day = $_POST['from'];
    $hour = $_POST['hour'];
    $ampm = $_POST['ampm'];
    $priority = $_POST['priority'];
    if(! isset($_POST['target'])){
      die ( "<h1>Error</h1> Please select whether to send to All or only certain buyers.<br><br><a href=\"email.php\">Go back.</a>" );
    }
    $target = $_POST['target'];
    $tolist = $_POST['tolist'];

    $hour = ( $ampm == 'pm') ? $hour + 12 : $hour ;
    $scheduled = $day . ' ' . $hour . ':00:00';
    $today = date("Y-m-d H:i:s");
    if($scheduled < $today){
      die ( "<h1>Error</h1> Can't schedule an email in the past! <br><br><a href=\"email.php\">Go back.</a>" );
    }

    $query = "INSERT INTO email_schedule (`start_time`,`content_id`, `priority`) VALUES ( ?, ?, ? )";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("sss", $scheduled, $content_id, $priority);
    $stmt->execute();
    $last_id = $mysqli->insert_id;
    $stmt->close();

    if($target == "all"){
      $tolist = '--ALL--';
      $sqlwhere = '';
    } else {
      $tolist = $mysqli->real_escape_string($tolist);
      $sqlwhere = "JOIN order_items oi ON o.id = oi.order_id WHERE oi.sku = '$tolist'";
    }

    $query = "INSERT INTO email_schedule_products (`email_schedule_id`, `sku`) VALUES ( ?, ? )";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ss", $last_id, $tolist);
    $stmt->execute();
    $stmt->close();

    # Now we put the individual addresses into email_mgmt table

    $query = "INSERT INTO email_mgmt(email, email_schedule_id) SELECT DISTINCT o.email, $last_id FROM orders o ". $sqlwhere;
    #die($query);

    $stmt = $mysqli->prepare($query);
    $stmt->execute();
    $number = $mysqli->affected_rows;
    $stmt->close();

    $mysqli->close();
    header("Location: email.php?s=$number");

  } elseif( $_POST['sent_from']=='add_content' ){

    $content = $_POST['body'];
    $subject = $_POST['subject'];
    $query = "INSERT INTO email_content (`subject`,`body`) VALUES ( ?, ? )";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ss", $subject, $content);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
    header('Location: email.php?page=content');

  } elseif( $_POST['sent_from']=='update_content' ){

    $id = $_POST['content_id'];
    $content = $_POST['content'];
    $subject = $_POST['subject'];
    $query = "UPDATE email_content SET body = ?, subject = ? WHERE id = ? ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("sss", $content, $subject, $id);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
    header('Location: email.php?page=content');

  } elseif( $_POST['sent_from']=='cancel' ){

    $id = $_POST['schedule_id'];
    $query = "UPDATE email_schedule SET status = 'cancelled' WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
    header('Location: email.php');

  }

} elseif( isset($_GET['page']) ){

  $page = $_GET['page'];

  if( $page == "actually_send" ){

    # this should be called automatically by crontab to send scheduled emails
    $id = 0;
    $query = "SELECT s.id, c.subject, c.body FROM email_schedule s JOIN email_content c ON s.content_id = c.id WHERE s.status = 'pending' AND s.time < NOW() LIMIT 1";
    if ($result = $mysqli->query($query)) {
      if ($row = $result->fetch_row()) {
        $id = $row[0];
        $subject = $row[1];
        $body = $row[2];
      }
    }
    $result->close();

    if($id){
      $sendlist = array();
      $query = "SELECT DISTINCT email FROM orders";
      if($result = $mysqli->query($query)) {
        while($row = $result->fetch_row()) {
          array_push($sendlist, $row[0]);
        }
        $list_count = count($sendlist);
        send_mail($subject, $body, $sendlist);
      }
      $result->close();
      $query = "UPDATE email_schedule SET status = 'sent', sent_count = ?  WHERE id = ?";
      $stmt = $mysqli->prepare($query);
      $stmt->bind_param("ss", $list_count, $id);
      $stmt->execute();
      $stmt->close();
      $mysqli->close();
      die("sent $list_count, done!");
    }
  }

  print "<a href=\"email.php?page=test_email\">Addresses for testing</a>
        | <a href=\"email.php?page=content\">Email Contents</a>
        | <a href=\"email.php\">Main</a>";

  if( $page == "test_email" ){

    print '<form action="email.php" method="POST">
           New Testing Email: <input type="text" name="new_email" />
           <input type="hidden" name="sent_from" value="add_test_email">
           <input type="submit" value="Add Test Email">
           </form><br><br>';
    $query = "SELECT address FROM email_test_addr ORDER BY address";
    if ($result = $mysqli->query($query)) {
      while ($row = $result->fetch_row()) {
        $this_addr = $row[0];
        print '<form action="email.php" method="POST">'.$this_addr.'
             <input type="hidden" name="del_email" value="'.$this_addr.'">
             <input type="hidden" name="sent_from" value="del_test_email">
             <input type="submit" value="Remove"></form>';

      }
      $result->close();
    }

  } elseif($page == "content"){

    if(isset($_GET['sent'])){
      $sent = $_GET['sent'];
    } else {
      $sent = 0;
    }
    print '<form action="email.php" method="POST">
           Subject: <input type="text" name="subject"/>
           <br>Body:<br><textarea name="body" rows=5 cols=50></textarea>
           <input type="hidden" name="sent_from" value="add_content">
           <br><input type="submit" value="Create"></form>';
    $query = "SELECT c.id, c.subject, c.body, s.status FROM email_content c LEFT JOIN email_schedule s ON s.content_id = c.id";
    if ($result = $mysqli->query($query)) {
      while ($row = $result->fetch_row()) {
        $id = $row[0];
        $subject = $row[1];
        $body = $row[2];
        $status = $row[3];
        print "<br><br>ID: $id<br>SUBJECT: $subject<br>$body<br>";
        if($sent == $id){
          print "Test Sent! || ";
        } else {
          print "<a href=\"email.php?page=send_test&id=$id\">Send Test</a> || ";
        }
        if(! $status || $status != 'sent' ){
          print "<a href=\"email.php?page=update&id=$id\">Update</a><br>";
        } else {
          print "Already Sent email shouldn't be changed<br>";
        }
      }
      $result->close();
    }

  } elseif( $page == 'update' ){

    $id = $_GET['id'];
    if ($stmt = $mysqli->prepare("SELECT subject, body FROM email_content where id = ?")){
      $stmt->bind_param("s", $id);
      $stmt->execute();
      $stmt->bind_result($subject, $body);
      while($stmt->fetch()){
        print '<form action="email.php" method="POST">
           <br>SUBJECT:<input type="text" name="subject" value="'.$subject.'"><br>
           <textarea name="content" rows=5 cols=60>'.$body.'</textarea>
           <input type="hidden" name="content_id" value="'.$id.'">
           <input type="hidden" name="sent_from" value="update_content">
           <input type="submit" value="Update"></form>';
      }
      $stmt->close();
    }

  } elseif( $page == 'send_test' ){

    $id = $_GET['id'];
    $sendlist = array();
    if ($stmt = $mysqli->prepare("SELECT subject, body FROM email_content where id = ?")){
      $stmt->bind_param("s", $id);
      $stmt->execute();
      $stmt->bind_result($subject, $body);
      while($stmt->fetch()){
        $this_subject = $subject;
        $this_body = $body;
      }
      $stmt->close();
    }
    if ($stmt = $mysqli->prepare("SELECT address FROM email_test_addr")){
      $stmt->execute();
      $stmt->bind_result($addr);
      while($stmt->fetch()){
        array_push( $sendlist, $addr );
      }
      $stmt->close();
    }

    send_mail($subject, $body, $sendlist);

    $query = "UPDATE email_content SET test_count = test_count + 1, last_test = NOW() WHERE id = ? ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
    header("Location: email.php?page=content&sent=$id");

  }

} else {

  # default page
  # Get an array of available messages
  $contents = array();
  $i = 0;
  $query = "SELECT id, LEFT(subject, 22) FROM email_content";
  if ($result = $mysqli->query($query)) {
    while ($row = $result->fetch_row()) {
      $contents[$i]['id'] = $row[0];
      $contents[$i]['subj'] = $row[1];
      $i++;
    }
    $result->close();
  }

  # Get an array of scheduled messages
  $schedules = array();
  $i = 0;
  $query = "SELECT s.id, s.start_time, s.content_id, s.status, s.sent_count, p.sku FROM email_schedule s JOIN email_schedule_products p ON s.id = p.email_schedule_id ORDER BY s.start_time";
  if ($result = $mysqli->query($query)) {
    while ($row = $result->fetch_row()) {
      $schedules[$i]['id'] = $row[0];
      $schedules[$i]['time'] = $row[1];
      $schedules[$i]['content_id'] = $row[2];
      $schedules[$i]['status'] = $row[3];
      $schedules[$i]['sent_count'] = $row[4];
      $schedules[$i]['sku'] = $row[5];
      $i++;
    }
    $result->close();
  }

  # Get array of SKUs and number sold
  $skus = array();
  $i = 0;
  $query = "SELECT count(o.id), s.seller_sku, LEFT(s.item_name,25) FROM sku_prices s JOIN order_items o ON s.seller_sku = o.sku GROUP BY s.id ORDER BY count(s.id) DESC";
  if ($result = $mysqli->query($query)) {
    while ($row = $result->fetch_row()) {
      $skus[$i]['count'] = $row[0];
      $skus[$i]['sku'] = $row[1];
      $skus[$i]['name'] = $row[2];
      $i++;
    }
    $result->close();
  }

  # see if we just scheduled a mail
  if(isset($_GET['s'])){
    $sch = $_GET['s'];
  } else {
    $sch = 0;
  }

  # start html output here, and bring in js for date picker:
  include('email_header.php');

  if( $sch ) {
    print "<h2>Scheduled $sch emails for delivery</h2>";
  }

  print "<a href=\"email.php?page=test_email\">Addresses for testing</a> 
        | <a href=\"email.php?page=content\">Email Contents</a>";
  print "<h4>Schedule an Email</h4>";
  print '<form action="email.php" method="POST">
         <input type="hidden" name="sent_from" value="schedule">
         Email Content ID: <select name="content_id">';
  foreach($contents as $content){
    $id = $content['id'];
    $subj = $content['subj'];
    print "           <option value=\"$id\">$id. $subj</option>";
  }

  print '
         </select> <br><br> Start Time (mail will not start before this time):<br>
         <input type="text" id="from" name="from" />
         <select name="hour">';
  for($i = 1; $i < 13; $i++){
    print "          <option value=\"$i\">$i</option>";
  }
  print '</select>
         <select name="ampm">
           <option value="am">AM</option>
           <option value="pm">PM</option>
         </select>
         <br>Priority:<select name="priority">
';
  for($i=1; $i<11; $i++){
    print "  <option value=\"$i\">$i</option>
";
  }
  print '</select> 1 goes first, 10 goes last<br>
         Target Group:<br>
         <input type="radio" name="target" value="all" /> All<br>
         <input type="radio" name="target" value="list" /> <select name="tolist">
';

  foreach($skus as $sku){
    $this_sku = $sku['sku'];
    $count = $sku['count'];
    $name = $sku['name'];
    print "<option value=\"$this_sku\">$count | $name | $this_sku</option>
"; 
  }

print '</select><br>
         <input type="Submit" value="Schedule">
         </form>
         <ul>
        ';
  foreach($schedules as $schedule){
    $this_id = $schedule['id'];
    $this_time = $schedule['time'];
    $content_id = $schedule['content_id'];
    $status = $schedule['status'];
    $sku = $schedule['sku'];
    if($status == 'sent'){
      $sent = $schedule['sent'];
    } else {
      $sent = 0;
    }
    
    print "<li><form action=\"email.php\" method=\"POST\">$this_time Content ID: $content_id Target List: $sku ";
    if($status == 'pending'){
      print 'pending <input type="hidden" name="sent_from" value="cancel">
        <input type="hidden" name="schedule_id" value="'.$this_id.'">
        <input type="submit" value="cancel">';
    } elseif($status == 'sent'){
      print "Sent: $sent ";
    } else {
      print "Cancelled";
    }
    print '</form></li><br>
          ';
  }

}

$mysqli->close();

function send_mail($subject, $body, $sendlist){
  foreach($sendlist as $addr){
    mail($addr, $subject, $body, "From:<support@omniherbals.com>");
    # wait 3 seconds between sending each email:
    sleep(3);
  }
}

?>
