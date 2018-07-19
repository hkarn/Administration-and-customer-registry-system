<?php

/**
 * Rekå Resor Bokningssystem
 * @author    Håkan Arnoldson
 */

namespace RekoBooking\classes\tours;

use RekoBooking\classes\DBError;
use RekoBooking\classes\Tokens;

class Category {

  public static function Get($jsonData, $response, $pdo) {
    $response->AddResponse('requestedid', $jsonData['categoryid']);
    try {  
      if ($jsonData['categoryid'] == 'all') {
        $sql = "SELECT * FROM Kategori ORDER BY Kategori";
        $sth = $pdo->prepare($sql);
      } else {
        $sql = "SELECT * FROM Kategori WHERE KategoriID =  :categoryid ORDER BY Kategori";
        $sth = $pdo->prepare($sql);
        $sth->bindParam(':categoryid', $jsonData['categoryid'], \PDO::PARAM_INT);
      }
      $sth->execute(); 
      $result = $sth->fetchAll(\PDO::FETCH_ASSOC);
    } catch(\PDOException $e) {
      DBError::showError($e, __CLASS__, $sql, $response);
      return false;
    }
    if (count($result) > 0) {
      foreach ($result as $category) {
        $active = $category['aktiv'] ? true : false;
        $response->AddToArrayOnKey('categories', 
          array('id' => (int)$category['kategoriid'], 'category' => $category['kategori'], 'active' => $active));
      }
      return true;
    } else {
      $response->AddResponse('response', 'Inga kategorier hittades');
      $response->AddResponse('categories', []);
      return false;
    }

  }

  public static function Delete($jsonData, $response, $pdo) {
    try {
      $sql = "SELECT Resa FROM Resa INNER JOIN Kategori_Resa ON Resa.ResaID = Kategori_Resa.ResaID 
        INNER JOIN Kategori ON Kategori_Resa.KategoriID = Kategori.KategoriID WHERE Kategori.KategoriID = :categoryid GROUP BY Resa.Resa";
      $sth = $pdo->prepare($sql);
      $sth->bindParam(':categoryid',   $jsonData['categoryid'],   \PDO::PARAM_INT);
      $sth->execute(); 
      $result = $sth->fetchAll(\PDO::FETCH_ASSOC);
    } catch(\PDOException $e) {
      DBError::showError($e, __CLASS__, $sql, $response);
      return false;
    }
    $usedin = "";
    if (count($result) > 0) {
      foreach ($result as $tour) {
        $usedin .= $tour['resa'] . ", ";
      }
      $usedin = trim($usedin, ", ");
      header( $_SERVER["SERVER_PROTOCOL"] . ' 409 Conflict');
      $headers = ob_get_clean();
      echo $headers;
      $response->AddResponse('response', "Det går inte att ta bort kategorin.\nKategorin används av följande resor:\n" . $usedin . ".\nDessa måste tas bort permanent först.");
      $response->AddResponse('conflict', true);
      return false;
    } 
    
    
    try {
      $sql = "DELETE FROM Kategori WHERE KategoriID = :categoryid";
      $sth = $pdo->prepare($sql);
      $sth->bindParam(':categoryid',   $jsonData['categoryid'],   \PDO::PARAM_INT);
      $sth->execute(); 
    } catch(\PDOException $e) {
      DBError::showError($e, __CLASS__, $sql, $response);
      return false;
    }
    $response->AddResponse('modifiedid', $jsonData['categoryid']);
    return true;
  }


  public static function Save($jsonData, $response, $pdo) {
   
    try {
      if ($jsonData['task'] == 'activetoggle') {
        $sql = "UPDATE Kategori SET Aktiv = :active WHERE KategoriID = :categoryid";
      } else {
        $sql = "UPDATE Kategori SET Kategori = :category, Aktiv = :active WHERE KategoriID = :categoryid";
        
      }
      
      $sth = $pdo->prepare($sql);
      $sth->bindParam(':categoryid',   $jsonData['categoryid'],   \PDO::PARAM_INT);
      if ($jsonData['task'] != 'activetoggle') {
        $sth->bindParam(':category', $jsonData['category'], \PDO::PARAM_STR);
      }      
      $sth->bindParam(':active',   $jsonData['active'],   \PDO::PARAM_INT);
      $sth->execute(); 
    } catch(\PDOException $e) {
      DBError::showError($e, __CLASS__, $sql, $response);
      return false;
    }
    $response->AddResponse('modifiedid', $jsonData['categoryid']);
    return true;
  }

  public static function New($jsonData, $response, $pdo) {

    try {
      if ($jsonData['categoryid'] == 'new') {
        $sql = "INSERT INTO Kategori (Kategori, Aktiv) OUTPUT INSERTED.KategoriID VALUES (:category, :active)";
      } else {
        $sql = "UPDATE Kategori SET Kategori = :category, Aktiv = :active) WHERE KategoriID = :categoryid";
      }
      $sth = $pdo->prepare($sql);
      if ($jsonData['categoryid'] != 'new') {
        $sth->bindParam(':categoryid',   $jsonData['categoryid'],   \PDO::PARAM_INT);
      }
      $sth->bindParam(':category', $jsonData['category'], \PDO::PARAM_STR);
      $sth->bindParam(':active',   $jsonData['active'],   \PDO::PARAM_INT);
      $sth->execute(); 
      $result = $sth->fetch(\PDO::FETCH_ASSOC);
    } catch(\PDOException $e) {
      DBError::showError($e, __CLASS__, $sql, $response);
      return false;
    }
    if ($jsonData['categoryid'] == 'new') {
      $response->AddResponse('modifiedid', (int)$result['kategoriid']);
    } else {
      $response->AddResponse('modifiedid', $jsonData['categoryid']);
    }
    
    return true;
  

  }

  public static function VerifyCategoryInput($jsonData, $response) {
    $newData = array();

    if (!empty($jsonData['user'])) {
      $newData['user'] = trim(filter_var($jsonData['user'], FILTER_SANITIZE_STRING));
    } else {
      $response->AddResponse('response', 'Inget användarnamn skickat.');
      return false;
    }

    if (!empty($jsonData['task'])) {
      $newData['task'] = trim(filter_var($jsonData['task'], FILTER_SANITIZE_STRING));
    } else {
      $newData['task'] = 'not set';
    }

    if (!empty($jsonData['category']) && trim($jsonData['category']) != false) {
      $newData['category'] = mb_strimwidth(filter_var(trim($jsonData['category']), FILTER_SANITIZE_STRING), 0, 60);
    } else {
      $response->AddResponse('response', 'Du måste ange ett namn för kategorin.');
      return false;
    }
    if (!empty($jsonData['categoryid'])) {
      $temp = filter_var(trim($jsonData['categoryid']), FILTER_SANITIZE_NUMBER_INT);
      $temp = filter_var($temp, FILTER_VALIDATE_INT);
      if (!$temp && $jsonData['categoryid'] !== 'new') {
        $response->AddResponse('response', 'Felformaterat kategoriID. Prova ladda om eller kontakta tekniker.');
        return false;
      } else {
        if ($jsonData['categoryid'] === 'new') {
          $newData['categoryid'] = 'new';
        } else {
          $newData['categoryid'] = $temp;
        }
      }
    } else {
      $newData['categoryid'] = 'new';
    }

    if (!empty($jsonData['active']) && filter_var($jsonData['active'], FILTER_VALIDATE_BOOLEAN)) {
      $newData['active']=1;
    } else {
      $newData['active']=0; 
    }
    
       
    return $newData;

  }


}