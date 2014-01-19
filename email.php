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
      $sqlwhere = 'WHERE ';
    } else {
      $tolist = $mysqli->real_escape_string($tolist);
      $sqlwhere = "JOIN order_items oi ON o.id = oi.order_id WHERE oi.sku = '$tolist' AND ";
    }

    $query = "INSERT INTO email_schedule_products (`email_schedule_id`, `sku`) VALUES ( ?, ? )";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ss", $last_id, $tolist);
    $stmt->execute();
    $stmt->close();

    # Now we put the individual addresses into email_mgmt table

    $query = "INSERT INTO email_mgmt(email, ref_id, email_schedule_id) SELECT o.email, o.amazon_id, $last_id FROM orders o " . $sqlwhere . "NOT EXISTS (SELECT m.email FROM email_mgmt m WHERE m.email = o.email AND m.status='blocked') GROUP BY o.email";
    #die($query);

    $stmt = $mysqli->prepare($query);
    $stmt->execute();
    $number = $mysqli->affected_rows;
    $stmt->close();

    $mysqli->close();
    header("Location: email.php?s=$number");

  } elseif( $_POST['sent_from']=='add_content' ){

    $body_text = $_POST['body_text'];
    $body_html = $_POST['body_html'];
    $subject = $_POST['subject'];
    $query = "INSERT INTO email_content (`subject`,`body_text`, `body_html`) VALUES ( ?, ?, ? )";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("sss", $subject, $body_text, $body_html);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
    header('Location: email.php?page=content');

  } elseif( $_POST['sent_from']=='update_content' ){

    $id = $_POST['content_id'];
    $body_text = $_POST['body_text'];
    $body_html = $_POST['body_html'];
    $subject = $_POST['subject'];
    $query = "UPDATE email_content SET body_text = ?, body_html = ?, subject = ? WHERE id = ? ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ssss", $body_text, $body_html, $subject, $id);
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

  } elseif( $_POST['sent_from']=='bounced' || $_POST['sent_from']=='blocked' ){

    $new_status = $_POST['sent_from'];
    $bounced = $_POST['bounced_email'];
    $notes = $_POST['notes'];
    $query = "SELECT id FROM email_mgmt WHERE email = ? ORDER BY send_time DESC LIMIT 1";
    if( $stmt = $mysqli->prepare($query) ){
      $stmt->bind_param("s", $bounced);
      $stmt->bind_result($id);
      $stmt->execute();
      while($stmt->fetch()){
        $this_id = $id;
      }
      $stmt->close();
    }
    $query = "UPDATE email_mgmt SET status = '?', note = '?' WHERE id = ?";
    if( $stmt = $mysqli->prepare($query) ){
      $stmt->bind_param("sss", $new_status, $notes, $this_id);
      $stmt->execute();
      $stmt->close();
    }
    $mysqli->close();
    header("Location: email.php?page=$new_status");
  }

} elseif( isset($_GET['page']) ){

  $page = $_GET['page'];

  if( $page == 'send_test' ){

    $id = $_GET['id'];
    $sendlist = array();
    if ($stmt = $mysqli->prepare("SELECT subject, body_text, body_html FROM email_content where id = ?")){
      $stmt->bind_param("s", $id);
      $stmt->execute();
      $stmt->bind_result($subject, $body_text, $body_html);
      while($stmt->fetch()){
        $this_subject = $subject;
        $this_body = $body_text;
        $this_html = $body_html;
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

    send_mail($this_subject, $this_body, $this_html, $sendlist);

    $query = "UPDATE email_content SET test_count = test_count + 1, last_test = NOW() WHERE id = ? ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
    header("Location: email.php?page=content&sent=$id");

  }

  print "<a href=\"email.php?page=test_email\">Addresses for testing</a>
        | <a href=\"email.php?page=content\">Email Contents</a>
        | <a href=\"email.php?page=bounced\">Bounces</a>
        | <a href=\"email.php?page=blocked\">Blocklist</a>
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

  } elseif( $page == "bounced" || $page == "blocked" ){

    $my_status = $page;
    print '<h2>'. ucfirst($my_status).' Addresses</h2><form action="email.php" method="POST">
           Set Email to '. $my_status .': <input type="text" name="bounced_email" /><br>
           Notes: <input type="text" name="notes" />
           <input type="hidden" name="sent_from" value="'. $my_status .'">
           <input type="submit" value="Update Email">
           </form><br><br>';
    $query = "SELECT email, note FROM email_mgmt WHERE status = \"$my_status\"";
    $bounces = array();
    if ($result = $mysqli->query($query)) {
      while ($row = $result->fetch_row()) {
        $this_row['email'] = $row[0];
        $this_row['notes'] = $row[1];
        array_push($bounces, $this_row);
      }
      $result->close();
      $total = count($bounces);
      print "<h3>$total $my_status addresses</h3><ul>";
      foreach($bounces as $addr){
        print "<li>".$addr['email'] ." ".  $addr['notes'] ."</li>
";
      }
      print "</ul>";
    }

  } elseif( $page == "content" ){

    $sent = (isset($_GET['sent'])) ? $_GET['sent'] : 0 ;

    print '<form action="email.php" method="POST">
           Subject: <input type="text" name="subject"/>
           <br>Text Body:<br><textarea name="body_text" rows=5 cols=50></textarea>
           <br>HTML Body (optional):<br><textarea name="body_html" rows=5 cols=50></textarea> 
           <input type="hidden" name="sent_from" value="add_content">
           <br><input type="submit" value="Create"></form>';
    #$query = "SELECT c.id, c.subject, c.body_text, c.body_html, s.status FROM email_content c LEFT JOIN email_schedule s ON s.content_id = c.id";
    $query = "SELECT c.id, c.subject, c.body_text, c.body_html FROM email_content c";
    if ($result = $mysqli->query($query)) {
      while ($row = $result->fetch_row()) {
        $id = $row[0];
        $subject = $row[1];
        $body_text = $row[2];
        $body_html = $row[3];

        #$status = $row[3];
        #if( $past_id != $id){

          print "<br><br>ID: $id<br>SUBJECT: $subject<br>$body_text<br><textarea>$body_html</textarea><br>";
          if($sent == $id){
            print "Test Sent! || ";
          } else {
            $r = date("YmdHis");;
            print "<a href=\"email.php?page=send_test&id=$id&r=$r\">Send Test</a> || ";
          }

          $this_status = 0;
          $statusquery = "SELECT id FROM email_schedule WHERE content_id = $id AND status = 'sent'";
          if($status = $mysqli->query($statusquery)){
            while($statusrow = $status->fetch_row()) { 
              $this_status = $statusrow[0];
            }
            $status->close();
          }

          if(! $this_status ){
            print "<a href=\"email.php?page=update&id=$id\">Update</a><br>";
          } else {
            print "Already Sent email shouldn't be changed<br>";
          }

        #  $past_id = $id;
        #}
      }
      $result->close();
    }

  } elseif( $page == 'update' ){

    $id = $_GET['id'];
    if ($stmt = $mysqli->prepare("SELECT subject, body_text, body_html FROM email_content where id = ?")){
      $stmt->bind_param("s", $id);
      $stmt->execute();
      $stmt->bind_result($subject, $body_text, $body_html);
      while($stmt->fetch()){
        print '<form action="email.php" method="POST">
           <br>SUBJECT:<input type="text" name="subject" value="'.$subject.'"><br>
           <textarea name="body_text" rows=5 cols=60>'.$body_text.'</textarea><br>
           <textarea name="body_html" rows=5 cols=60>'.$body_html.'</textarea>
           <input type="hidden" name="content_id" value="'.$id.'">
           <input type="hidden" name="sent_from" value="update_content">
           <input type="submit" value="Update"></form>';
      }
      $stmt->close();
    }

  } elseif( $page == 'send_test' ){

    $id = $_GET['id'];
    $sendlist = array();
    if ($stmt = $mysqli->prepare("SELECT subject, body_text, body_html FROM email_content where id = ?")){
      $stmt->bind_param("s", $id);
      $stmt->execute();
      $stmt->bind_result($subject, $body_text, $body_html);
      while($stmt->fetch()){
        $this_subject = $subject;
        $this_body = $body_text;
        $this_html = $body_html;
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

    #die("Sending $this_subject, $this_body, $this_html to ".count($sendlist));
    #exit();

    send_mail($this_subject, $this_body, $this_html, $sendlist);

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
    # Loop over schedules to look for any pending or sending; get their pending count
    $i = 0;
    foreach($schedules as $schedule){
      if( in_array($schedule['status'], array("pending","sending") ) ){
        $this_id = $schedule['id'];
        $query = "SELECT COUNT(id) FROM email_mgmt WHERE email_schedule_id = $this_id AND status = 'pending'";
        if( $stmt = $mysqli->prepare($query) ){
          $stmt->execute();
          $stmt->bind_result($p_count);
          while($stmt->fetch()){
            $schedules[$i]['pending_count'] = $p_count;
          }
          $stmt->close();
        }
      }
      $i++;
    }
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
  $sch = (isset($_GET['s'])) ? $_GET['s'] : 0;

  # start html output here, and bring in js for date picker:
  include('email_header.php');

  if( $sch ) {
    print "<h2>Scheduled $sch emails for delivery</h2>";
  }

  print "<a href=\"email.php?page=test_email\">Addresses for testing</a> 
        | <a href=\"email.php?page=content\">Email Contents</a>
        | <a href=\"email.php?page=bounced\">Bounces</a>
        | <a href=\"email.php?page=blocked\">Blocklist</a>";
  print "<h4>Schedule an Email</h4>";
  print '<form action="email.php" method="POST">
         <input type="hidden" name="sent_from" value="schedule">
         Email Content ID: <select name="content_id">';
  $contents = array_reverse( $contents );
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
    $pending_count = $schedule['pending_count'];
    $sent_count = $schedule['sent_count'];

    print "<li><form action=\"email.php\" method=\"POST\">$this_time Content ID: $content_id Target List: $sku ";
    if($status == 'pending' || $status == 'sending'){
      print $status.' <input type="hidden" name="sent_from" value="cancel">
        <input type="hidden" name="schedule_id" value="'.$this_id.'">
        <input type="submit" value="cancel">';
    } elseif($status == 'sent'){
      print "Complete";
    } else {
      print "Cancelled";
    }

    if($sent_count){ print " sent: $sent_count "; }
    if($pending_count){ print "  pending: $pending_count "; }

    print '</form></li>
          ';
  }

}

$mysqli->close();


function send_mail($subject, $body_text, $body_html, $sendlist){

  #die("Sending $subject, $body_text, $body_html to ".count($sendlist));
  #exit();

  # include pear Mail packages
  include 'Mail.php';
  include 'Mail/mime.php';

  $crlf = "\n";
  $hdrs = array(
              'From'    => 'Support@omniherbals.com',
              'Subject' => $subject
              );
  $mime = new Mail_mime(array('eol' => $crlf));

  $mime->setTXTBody($body_text);
  $mime->setHTMLBody($body_html);

  $body = $mime->get();
  $hdrs = $mime->headers($hdrs);

  $mail =& Mail::factory('mail');

  foreach($sendlist as $addr){
    $mail->send($addr, $hdrs, $body);
    # wait 2 seconds between sending each email:
    sleep(2);
  }
}

?>
