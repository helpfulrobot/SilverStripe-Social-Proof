<?php

/**
 * @author Kirk Mayo <kirk.mayo@solnet.co.nz>
 *
 * A service to retrieve Google Plus interactions for a url
 */
class GooglePlusCount extends SocialServiceCount implements SocialServiceInterface {

    public $entry;
    public $service = 'Google';
    public $statistic = 'count';

    function processQueue(){
        try {
            foreach ($this->queue as $entry) {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, "https://clients6.google.com/rpc");
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, '[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"'
                    . $entry['URL'] .
                    '","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]');
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
                $curl_results = curl_exec ($curl);

                if(curl_errno($curl)) {
                    $this->errorQueue[] = $entry['URL'];
                    continue;
                }
                curl_close ($curl);

                $json = json_decode($curl_results, true);

                if (isset($json['error'])) {
                    $this->errorQueue[] = $entry['URL'];
                    continue;
                }

                $count = intval( $json[0]['result']['metadata']['globalCounts']['count'] );
                $id = $entry['ID'];
                $entry = SocialQueue::get_by_id('SocialQueue',$id);
                $statistic = URLStatistics::get()
                    ->filter(array(
                        'URLID' => $entry->URLID,
                        'Service' => $this->service,
                        'Action' => $this->statistic
                    ))->first();
                if (!$statistic || !$statistic->exists()) {
                    $statistic = URLStatistics::create();
                    $statistic->URLID = $entry->URLID;
                    $statistic->Service = $this->service;
                    $statistic->Action = $this->statistic;
                }
                $statistic->Count = $count;
                $statistic->write();
                $entry->Queued = 0;
                $entry->write();
            }

        } catch (Exception $e) {
            return 0;
        }
        return $this->errorQueue;
    }
}
