<?php
    header('Content-Type: text/html; charset=utf-8');
    
    // YOUR MASTODON TOKEN (Settings -> Development -> New application)
    $token = "[YOUR MASTODON TOKEN GOES HERE]";

    // YOUR MASTODON INSTANCE URL
    $instance_url = "[YOUR MASTODON INSTANCE URL GOES HERE]";
    // DEFINE YOUR MASTODON INSTANCE RATE LIMIT (MAX NUMBER OF LETTERS AVAILABLE IN ONE TOOT)
    $instance_rate_limit = 500;
    
    // RSS URLS (YOU CAN USE MULTIPLE THEN SEPARATE THEM WITH COMMAS)
    $urls = array(
        "https://www.rp.pl/rss_main"
    );

    // FILE WITH ALREADY POSTED ARTICLES (COUNTAIN LINKS)
    $file = file_get_contents("rzeczpospolita.txt");

    // PROCEED THROUGH EVERY URL ONE BY ONE
    foreach($urls as $url)
    {
        // LOAD A XML FILE (RSS FEED)
        $feeds = simplexml_load_file($url);

        if(!empty($feeds))
        {
            // SPLIT FEED INTO SEPARATE ITEMS (ARTICLES)
            foreach ($feeds->channel->item as $item)
            {
                // CONVERT LINK TO A STRING VARIABLE (WITHOUT THAT STR_CONTAINS GIVES ERROR)
                $link = strval($item->link);
                // REMOVE CDATA TAG FROM LINK
                $link = str_replace("<![CDATA[", "", $link);
                $link = str_replace("]]>", "", $link);

                if(str_contains($file, $link))
                {
                    // IF LINK IS IN A FILE THEN THIS ARTICLE HAS BEEN ALREADY POSTED SO SKIP IT AND GO TO NEXT ONE
                    continue;
                }
                else
                {
                    // GET TITLE
                    $title = strval($item->title);
                    // REMOVE CDATA TAG FROM TITLE
                    $title = str_replace("<![CDATA[", "", $title);
                    $title = str_replace("]]>", "", $title);
                    // GET DESCRPTION
                    $description = strval(strip_tags($item->description));
                    // REMOVE CDATA TAG FROM DESCRIPTION
                    $description = str_replace("<![CDATA[", "", $description);
                    $description = str_replace("]]>", "", $description);
                    // CLEAR ARRAY WITH HASHTAGS
                    $hashtag = array();
                    // GET CATEGORIES (MULTIPLE SO GO THROUGH ALL OF THEM ONE BY ONE) 
                    foreach($item->category as $category)
                    {
                        // FIRST CONVERT ALL WORDS TO LOWERCASE AND THEN MAKE UPPERCASE FIRST LETTERS IN EVERY WORD
                        $category = ucwords(strtolower(strval($category)));
                        // REMOVE SPACES BETWEEN WORDS
                        $category = str_replace(" ", "", $category);
                        // ADD HASHTAG SIGN AT THE BEGINNING OF EVERY CATEGORY AND "MEWS" AT THE END TO MAKE HASHTAG UNIQUE
                        $category = "#".$category."MEWS";
                        // PUSH REFORMATTED CATEGORY INTO ARRAY WITH HASHTAGS
                        $hashtag[] = $category;
                    }
                    $hashtag[] = "#MEWS";
                    // GATHER ALL HASHTAGS TOGETHER INTO ONE STRING WITH SPACE AS SEPARATOR
                    $hashtags = implode(" ", $hashtag);

                    // COMPOSE A TOOT WITH LESS THAN 500 LETTERS
                    // CALCULATE HOW LONG CAN BE THE DESCRIPTION (INSTANCE RATE LIMIT - TITLE - SEPARATOR - 6X EOL - HASHTAGS - LINK - 3X DOTS - 10X RESERVE)
                    $description_limit = $instance_rate_limit - strlen($title) - 3 - 6 - strlen($hashtags) - strlen($link) - 3 - 10;
                    // CHECK LENGTH OF DESCRIPTION AND SHORTEN IF NEEDED
                    if(strlen($description) > $description_limit)
                    {
                        $description = substr($description,0,$description_limit);
                        $description .= "...";
                    }
                    
                    // CONNECT ALL THE ELEMENTS
                    $status_message = $title."\r\n";
                    $status_message .= "-----"."\r\n";
                    $status_message .= $hashtags."\r\n";
                    $status_message .= "-----"."\r\n";
                    $status_message .= $description."\r\n\r\n";
                    $status_message .= $link;

                    // PUBLISH A NEW ARTICLE ON MASTODON
                    $status_data = array(
                        "status" => $status_message,
                        "language" => "eng",
                        "visibility" => "unlisted"
                    );

                    $headers = [
                        "Authorization: Bearer ".$token
                    ];
                    
                    $ch_status = curl_init();
                    curl_setopt($ch_status, CURLOPT_URL, $instance_url."/api/v1/statuses");
                    curl_setopt($ch_status, CURLOPT_POST, 1);
                    curl_setopt($ch_status, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_status, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch_status, CURLOPT_POSTFIELDS, $status_data);
                    
                    $output_status = json_decode(curl_exec($ch_status));
                    
                    curl_close ($ch_status);
                    
                    // PUSH LINK INTO A VARIABLE WITH LINKS TO ALREADY PUBLISHED ARTICLES
                    $file .= $link."\n";
                }
            }
        }
    }

    // UPDATE THE FILE WITH LIST OF PUBLISHED ARTICLES
    file_put_contents("rzeczpospolita.txt", $file);
?>
