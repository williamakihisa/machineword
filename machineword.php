<?php
set_time_limit(0);
ini_set("memory_limit","2048M");
require_once __DIR__ . '/naivebayes/vendor/autoload.php';

class Machineword
{
    public $index;

    /**
     * constructor setting the config variables for server ip and index.
     */
    // protected $public $allow_type = array('text','date','long','integer','float');
    public function __construct()
    {
        $ci = &get_instance();
        $this->stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
        $this->stemmer  = $this->stemmerFactory->createStemmer();
        $this->registered = array('elektronik-konsumen','hobi','fashion','fotografi','handphone','komputer','mobil','motor','properti','tv-audio','sepeda');
        $this->limit_search_training = 10;
    }

    public function analyze($string = '', $trainingflag = 1){
      $result = array(
        'status' => 0,
        'section' => '',
        'time' => 0,
        'training_mode' => false
      );
      $time_start = microtime(true);
      //simplify words with stemmer
      $stemmed   = $this->stemmer->stem($string);
      $splitword = explode(" ",$stemmed);
      $processedword = $this->keyword_processor($splitword);

      if ($processedword['status'] == 1){
         $result['status'] = 1;
         $result['section'] = $processedword['section'];
      }else if ($trainingflag == 1){
         //begin training mode
         $result['training_mode'] = true;
         $checkTraining = $this->training_words($processedword['training_word']);
         if ($checkTraining['status'] == 1){
            $result['status'] = 1;
            $result['section'] = $checkTraining['section'];
         }else{
            $result['status'] = 0;
         }
      }else{
        $result['status'] = 0;
      }
      /*
      1. split stem into array
      method keyword processor
      2. remove supportive words or adjecent
      3. find words into json keyword
      4. if find = 1 section then show the sections
      5. if find > 1 section then calculate % to show the largest section keyword
         if % = 50 then calculate keyword number to show (DONE)

      method training words (NEXT)
      6. if find = 0 section then store into training json
         a--find each words into elastic by title, limit per 5 article ( can be change by threshold for accuracy and speed ) with pagination
         b--search each article into json keyword again repeat the step (4,5)
         c--if words find then calculate %

      method memorize words
         d--if % > 50 then store into JSON
         e--if % = % then repeat process a with continue the pagination
      */
      $result['time'] = number_format((microtime(true) - $time_start), 2);
      return $result;
    }

    private function keyword_processor($stemword = array()){
      $result = array(
        'status' => 0,
        'section' => '',
        'training_word' => array(),
        'time' => 0
      );

      $spprtv = json_decode(file_get_contents(__DIR__ . '/keyword/supportive.json'), true);
      for ($i=0; $i < count($stemword); $i++) {
        foreach ($spprtv as $fltr) {
            if (strtolower($stemword[$i]) == strtolower($fltr)){
              unset($stemword[$i]);
            }
        }
      }
      $rankkey = $filterWord = $filterpercent = array();

      foreach ($stemword as $keyst => $valuest) {
        $filterWord[] = $valuest;
      }

      $filterWord = array_unique($filterWord);

      // echo json_encode($filterWord);die();
      //start find by section

      for ($j=0; $j < count($this->registered); $j++) {
          $checkkey = json_decode(file_get_contents(__DIR__ . '/keyword/'.$this->registered[$j].'.json'), true);
          for ($k=0; $k < count($filterWord); $k++) {
            foreach ($checkkey as $checkword) {
               if (preg_match('/^'.$checkword.'/im', $filterWord[$k])) {
               // if (strtolower($filterWord[$k]) == strtolower($checkword)){
                  $filterpercent[$this->registered[$j]][] = $filterWord[$k];
               }
            }
          }
      }
      // echo json_encode($filterpercent);die();
      //ensure no count percent same
      $percentcheck = array();
      foreach ($filterpercent as $keyprc => $valueprc) {
          $rankkey[count($valueprc)] = $keyprc;
      }

      krsort($rankkey);

      //ensure no count
      // echo json_encode($rankkey);die();
      if (count($rankkey) > 0){
         //get first key
         $result['section'] = '';
         $result['status'] = 1;
         foreach ($rankkey as $keyfin => $valuefin) {
            if ($result['section'] == ''){
               $result['section'] = $valuefin;
            }else{
              break;
            }
         }
      }else{
        //go to training mode
        $result['status'] = 2;
        $result['training_word'] = $filterWord;
      }

      return $result;
    }

    private function training_words($filterWord = array()){
      //find first in training list
      /*
      {
       [
         "word": "jokowi",
         "suggestion": {
          "properti": 2,
          "mobil": 1
          },
          "training_word": "jokowi lantik",
          "hit": 0
       ]
      }
      */
      $result = array(
        'status' => 0,
        'section' => ''
      );
       $foundSuggest = array();
       for ($o=0; $o < count($filterWord); $o++) {
         if (count($foundSuggest) == 0){
           $fromTraining = 0;
           $getTrainingwords = json_decode(file_get_contents(__DIR__ . '/keyword/training.json'), true);
           if (count($getTrainingwords) > 0){
             for ($i=0; $i < count($getTrainingwords); $i++) {
                if (strtolower($filterWord[$o]) == $getTrainingwords[$i]['word'] && isset($getTrainingwords[$i]['suggestion'])){
                   $foundSuggest = $getTrainingwords[$i]['suggestion'];
                   $getTrainingwords[$i]['hit'] = intval($getTrainingwords[$i]['hit']) + 1;
                   $fromTraining = 1;
                }
             }
           }
           //attempted to check on external source if not found
           if (count($foundSuggest) == 0){
              $searchTitle = $this->searchword(strtolower($filterWord[$o]));
              foreach ($searchTitle as $titleFind) {
                $stemmed   = $this->stemmer->stem($titleFind);
                $splitword = explode(" ",$stemmed);
                $datafind = $this->keyword_processor($splitword);
                if ($datafind['status'] == 1){
                   $foundSuggest[] = $datafind['section'];
                 }
              }
           }
           //update training words
           if (count($foundSuggest) > 0){
             if ($fromTraining == 0){
               $lastTr = count($getTrainingwords);
               $newTraining = array(
                 'word' => strtolower($filterWord[$o]),
                 'suggestion' => $foundSuggest,
                 'training_word' => implode(" ",$filterWord),
                 'hit' => 1
               );
               $getTrainingwords[$lastTr] = $newTraining;
             }
             file_put_contents(__DIR__ . '/keyword/training.json', json_encode($getTrainingwords, JSON_PRETTY_PRINT));
           }
         }else{
           break;
         }
       }
       if (count($foundSuggest) > 0){
         $result['status'] = 1;
         if (count($foundSuggest) == 1){
           $result['section'] = $foundSuggest[0];
         }else{
           $multiSuggest = array_count_values($foundSuggest);
           arsort($multiSuggest);
           reset($multiSuggest);
           $result['section'] = key($multiSuggest);
         }

       }
       //give suggestion
       return $result;
    }

    private function searchword($word = ''){
      //this can be change if there's any new source for search
      $newsource = array();

      return $newsource;
    }
}
