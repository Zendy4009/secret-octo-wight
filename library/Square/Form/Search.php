<?php
class Square_Form_Search extends Zend_Form
{
  public function init()
  {
    // initialize form
    $this->setAction('/catalog/item/search')
         ->setMethod('get');
         
    // create text input for keywords
    $query = new Zend_Form_Element_Text('q');
    $query->setLabel('Keywords:')
             ->setOptions(array('size' => '20'))
             ->addFilter('HtmlEntities')            
             ->addFilter('StringTrim');            
    $query->setDecorators(array(
                array('ViewHelper'),
                array('Errors'),
                array('Label', array('tag' => '<span>')),
             ));
             
    // create submit button
    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel('Search')
           ->setOptions(array('class' => 'submit'));
    $submit->setDecorators(array(
                array('ViewHelper'),
             ));
         
    // attach elements to form    
    $this->addElement($query)
         ->addElement($submit);    
  }
 
}
