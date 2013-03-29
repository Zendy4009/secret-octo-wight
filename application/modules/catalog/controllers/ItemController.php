<?php
class Catalog_ItemController extends Zend_Controller_Action
{
  public function init()
  {
    $this->view->doctype('XHTML1_STRICT');
    // initialize context switch helper
    $contextSwitch = $this->_helper->getHelper('contextSwitch');
    $contextSwitch->addActionContext('search', 'xml')
                  ->initContext();
  }


  // action to display a catalog item
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
            ->where('i.RecordID = ?', $input->id)
            ->addWhere('i.DisplayStatus = 1')
            ->addWhere('i.DisplayUntil >= CURDATE()');
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
  
  public function createAction()
  {
    // generate input form
    $form = new Square_Form_ItemCreate;
    $this->view->form = $form;
    
    // test for valid input
    // if valid, populate model
    // assign default values for some fields
    // save to database
    if ($this->getRequest()->isPost()) {
      if ($form->isValid($this->getRequest()->getPost())) {
        $item = new Square_Model_Item;
        $item->fromArray($form->getValues());      
        $item->RecordDate = date('Y-m-d', mktime());
        $item->DisplayStatus = 0;
        $item->DisplayUntil = null;
        $item->save();
        $id = $item->RecordID;  
        $this->_helper->getHelper('FlashMessenger')->addMessage('Your submission has been accepted as item #' . $id . '. A moderator will review it and, if approved, it will appear on the site within 48 hours.');
        $this->_redirect('/catalog/item/success');
      }   
    } 
  }
  
  // action to perform full-text search
  public function searchAction()
  {
    // generate input form
    $form = new Square_Form_Search;
    $this->view->form = $form;

    // get items matching search criteria    
    if ($form->isValid($this->getRequest()->getParams())) {
      $input = $form->getValues();    
      if (!empty($input['q'])) {
        $config = $this->getInvokeArg('bootstrap')->getOption('indexes');
        $index = Zend_Search_Lucene::open($config['indexPath']);      
        $results = $index->find(Zend_Search_Lucene_Search_QueryParser::parse($input['q']));   
        $this->view->results = $results;
      }
    }
  }

  public function successAction()
  {
    if ($this->_helper->getHelper('FlashMessenger')->getMessages()) {
      $this->view->messages = $this->_helper->getHelper('FlashMessenger')->getMessages();    
    } else {
      $this->_redirect('/');    
    } 
  }  
  
}
