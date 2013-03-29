<?php
class Catalog_AdminItemController extends Zend_Controller_Action
{
  public function init() 
  {
    $this->view->doctype('XHTML1_STRICT');
  }
  
  // action to handle admin URLs
  public function preDispatch() 
  {
    // set admin layout
    // check if user is authenticated
    // if not, redirect to login page
    $url = $this->getRequest()->getRequestUri();
    $this->_helper->layout->setLayout('admin');          
    if (!Zend_Auth::getInstance()->hasIdentity()) {
      $session = new Zend_Session_Namespace('square.auth');
      $session->requestURL = $url;
      $this->_redirect('/admin/login');
    }
  }
  
  // action to display list of catalog items
  public function indexAction()
  {
    $q = Doctrine_Query::create()
          ->from('Square_Model_Item i')
          ->leftJoin('i.Square_Model_Grade g')
          ->leftJoin('i.Square_Model_Country c')
          ->leftJoin('i.Square_Model_Type t');
    $result = $q->fetchArray();
    $this->view->records = $result; 
  }

  // action to delete catalog items
  public function deleteAction()
  {
    // set filters and validators for POST input
    $filters = array(
      'ids' => array('HtmlEntities', 'StripTags', 'StringTrim')
    );    
    $validators = array(
      'ids' => array('NotEmpty', 'Int')
    );
    $input = new Zend_Filter_Input($filters, $validators);
    $input->setData($this->getRequest()->getParams());
    
    // test if input is valid
    // read array of record identifiers
    // delete records from database
    if ($input->isValid()) {
      $q = Doctrine_Query::create()
            ->delete('Square_Model_Item i')
            ->whereIn('i.RecordID', $input->ids);
      $result = $q->execute();          
      $this->_helper->getHelper('FlashMessenger')->addMessage('The records were successfully deleted.');
      $this->_redirect('/admin/catalog/item/success');
    } else {
      throw new Zend_Controller_Action_Exception('Invalid input');              
    }
  }
  
  // action to modify an individual catalog item
  public function updateAction()
  {
    // generate input form
    $form = new Square_Form_ItemUpdate;
    $this->view->form = $form;    
    
    if ($this->getRequest()->isPost()) {
      // if POST request
      // test if input is valid
      // retrieve current record
      // update values and replace in database
      $postData = $this->getRequest()->getPost();
      $postData['DisplayUntil'] = sprintf('%04d-%02d-%02d', 
        $this->getRequest()->getPost('DisplayUntil_year'), 
        $this->getRequest()->getPost('DisplayUntil_month'), 
        $this->getRequest()->getPost('DisplayUntil_day')
      );      
      if ($form->isValid($postData)) {
        $input = $form->getValues();
        $item = Doctrine::getTable('Square_Model_Item')->find($input['RecordID']);        
        $item->fromArray($input);
        $item->DisplayUntil = ($item->DisplayStatus == 0) ? null : $item->DisplayUntil;
        $item->save();
        $this->_helper->getHelper('FlashMessenger')->addMessage('The record was successfully updated.');
        $this->_redirect('/admin/catalog/item/success');        
      }      
    } else {    
      // if GET request
      // set filters and validators for GET input
      // test if input is valid
      // retrieve requested record
      // pre-populate form
      $filters = array(
        'id' => array('HtmlEntities', 'StripTags', 'StringTrim')
      );          
      $validators = array(
        'id' => array('NotEmpty', 'Int')
      );  
      $input = new Zend_Filter_Input($filters, $validators);
      $input->setData($this->getRequest()->getParams());      
      if ($input->isValid()) {
        $q = Doctrine_Query::create()
              ->from('Square_Model_Item i')
              ->leftJoin('i.Square_Model_Country c')
              ->leftJoin('i.Square_Model_Grade g')
              ->leftJoin('i.Square_Model_Type t')
              ->where('i.RecordID = ?', $input->id);
        $result = $q->fetchArray();        
        if (count($result) == 1) {
          // perform adjustment for date selection lists
          $date = $result[0]['DisplayUntil'];
          $result[0]['DisplayUntil_day'] = date('d', strtotime($date));
          $result[0]['DisplayUntil_month'] = date('m', strtotime($date));
          $result[0]['DisplayUntil_year'] = date('Y', strtotime($date));
          $this->view->form->populate($result[0]);                
        } else {
          throw new Zend_Controller_Action_Exception('Page not found', 404);        
        }        
      } else {
        throw new Zend_Controller_Action_Exception('Invalid input');                
      }              
    }
  }  
  
  // action to display an individual catalog item
  public function displayAction()
  {
    // set filters and validators for GET input
    $filters = array(
      'id' => array('HtmlEntities', 'StripTags', 'StringTrim')
    );    
    $validators = array(
      'id' => array('NotEmpty', 'Int')
    );
    $input = new Zend_Filter_Input($filters, $validators);
    $input->setData($this->getRequest()->getParams());

    // test if input is valid
    // retrieve requested record
    // attach to view
    if ($input->isValid()) {
      $q = Doctrine_Query::create()
            ->from('Square_Model_Item i')
            ->leftJoin('i.Square_Model_Country c')
            ->leftJoin('i.Square_Model_Grade g')
            ->leftJoin('i.Square_Model_Type t')
            ->where('i.RecordID = ?', $input->id);
      $result = $q->fetchArray();
      if (count($result) == 1) {
        $this->view->item = $result[0];               
      } else {
        throw new Zend_Controller_Action_Exception('Page not found', 404);        
      }
    } else {
      throw new Zend_Controller_Action_Exception('Invalid input');              
    }
  }      

  // action to create full-text indices
  public function createFulltextIndexAction()
  {
    // create and execute query
    $q = Doctrine_Query::create()
          ->from('Square_Model_Item i')
          ->leftJoin('i.Square_Model_Country c')
          ->leftJoin('i.Square_Model_Grade g')
          ->leftJoin('i.Square_Model_Type t')
          ->where('i.DisplayStatus = 1')
          ->addWhere('i.DisplayUntil >= CURDATE()');
    $result = $q->fetchArray();
    
    // get index directory
    $config = $this->getInvokeArg('bootstrap')->getOption('indexes');
    $index = Zend_Search_Lucene::create($config['indexPath']);
    
    foreach ($result as $r) {
      // create new document in index
      $doc = new Zend_Search_Lucene_Document();

      // index and store fields
      $doc->addField(Zend_Search_Lucene_Field::Text('Title', $r['Title']));
      $doc->addField(Zend_Search_Lucene_Field::Text('Country', $r['Square_Model_Country']['CountryName']));
      $doc->addField(Zend_Search_Lucene_Field::Text('Grade', $r['Square_Model_Grade']['GradeName']));
      $doc->addField(Zend_Search_Lucene_Field::Text('Year', $r['Year']));      
      $doc->addField(Zend_Search_Lucene_Field::UnStored('Description', $r['Description']));
      $doc->addField(Zend_Search_Lucene_Field::UnStored('Denomination', $r['Denomination']));
      $doc->addField(Zend_Search_Lucene_Field::UnStored('Type', $r['Square_Model_Type']['TypeName']));
      $doc->addField(Zend_Search_Lucene_Field::UnIndexed('SalePriceMin', $r['SalePriceMin']));
      $doc->addField(Zend_Search_Lucene_Field::UnIndexed('SalePriceMax', $r['SalePriceMax']));
      $doc->addField(Zend_Search_Lucene_Field::UnIndexed('RecordID', $r['RecordID']));

      // save result to index
      $index->addDocument($doc);      
    }

    // set number of documents in index
    $count = $index->count();
    $this->_helper->getHelper('FlashMessenger')->addMessage("The index was successfully created with $count documents.");
    $this->_redirect('/admin/catalog/item/success');    
  }
  
  // success action
  public function successAction()
  {
    if ($this->_helper->getHelper('FlashMessenger')->getMessages()) {
      $this->view->messages = $this->_helper->getHelper('FlashMessenger')->getMessages();    
    } else {
      $this->_redirect('/admin/catalog/item/index');    
    } 
  }
  
    
}
