<?php
class Censor
{
    private $words;
    private $filter_count;
    private $lemmatize;
    private $db;
    public function __construct($pdo, $lemmatize = false)
    {
        $this->lemmatize = $lemmatize;
        $this->db = $pdo;
        $stmt = $this->db->prepare("SELECT count(*) FROM swears_word");
        $stmt->execute();
        $this->filter_count = $stmt->fetchAll(PDO::FETCH_COLUMN, 0)[0];
    }
    // return nothing
    private function get_words($page, $perPage)
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("SELECT words FROM swears_word LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        $this->words = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        return $this->words;
    }
    // return string
    private function lemmatize_input($text)
    {
        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => "https://hazm.sobhe.ir/api/correct",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ["text" => $text, "mode" => "cleaner"]
            )
        );

        $correct = curl_exec($curl);

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => "https://hazm.sobhe.ir/api/normalize",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ["text" => $correct]
            )
        );

        $normalize = curl_exec($curl);

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => "https://hazm.sobhe.ir/api/tokenize",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ["normalized_text" => $normalize]
            )
        );

        $tokenize = curl_exec($curl);

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => "https://hazm.sobhe.ir/api/lemmatize",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ["tokenized_text" => $tokenize]
            )
        );

        $lemmatize = curl_exec($curl);

        curl_close($curl);
        return $lemmatize;
    }

    // return string
    private function filter_words($text, $symbol = "*")
    {
        if (count($this->words) < 1)
            return $text;

        $filterCount = sizeof((array) $this->words);

        for ($i = 0; $i < $filterCount; $i++) {
            $text = preg_replace('[' . $this->words[$i] . ']', str_repeat($symbol, strlen('$0')), $text);
        }
        return $text;
    }
    
    // return string
    public function Clean($text)
    {
        if ($this->lemmatize) {
            $text = $this->lemmatize_input($text);
        }
        $text_splitted = preg_split("/[\s,]+/", $text);
        $page_count = 10;
        $pages = ceil($this->filter_count * 0.1);
        $offset = 1;
        $text = "";
        while ($page_count > 0) {
            $this->get_words($offset, $pages);
            foreach ($text_splitted as $val) {
                $text .= ltrim(rtrim($this->filter_words($text, "*")));
            }
            $offset += 1;
            $page_count--;
        }
        return $text;
    }
}