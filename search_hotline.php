<?php
include 'db.php';

if(isset($_POST['query'])){
    $query = $conn->real_escape_string($_POST['query']);
    $sql = "SELECT hotline_name, hotline, total_queues, total_agents 
            FROM hotlines 
            WHERE hotline_name LIKE '%$query%' 
            LIMIT 10";
    $res = $conn->query($sql);

    if($res && $res->num_rows > 0){
        while($row = $res->fetch_assoc()){
            echo "<div class='autocomplete-suggestion' 
                      data-number='{$row['hotline']}' 
                      data-queues='{$row['total_queues']}' 
                      data-agents='{$row['total_agents']}'>
                    {$row['hotline_name']}
                  </div>";
        }
    } else {
        echo "<div class='autocomplete-suggestion'>No matches found</div>";
    }
}
?>