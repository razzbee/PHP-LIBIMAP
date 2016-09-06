<?php
/**
 * *******************************************************
 * User IMAP Class
 *@author Razak Zakari <razzsense@gmail.com>
 *@copyright  Copyright [2016] [Razak Zakari]

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
 *************************************************/

namespace PHPLibImap;

use \Exception AS Exception;

class IMAP
{
    
    /**IMAP Connection **/
    private $imapConn;

    /**Connection String**/
    private  $connString;

    /**Host of the current IMAP Connection **/
    protected $host;

    /**Port of the current IMAP Connection**/
    protected $port;

    /**Current MailBox Folder Name **/
    protected $mailBoxName;

    /**Username (Email Address) of Current IMAP Connection **/
    protected $imapUsername;

    /**Password of current IMAP Connection **/
    protected $imapPassword;

    /**No Validate ssl **/
    protected $noValidateSSL;

    /**Conn Retries **/
    protected $connRetries;

    /**MailBoxInfo**/
    private $mailBoxInfo = [];

    /**sortBy**/
    //private $sortBy = "uid";

    /**Sort Direction **/
    private $sortDirection = "desc";

   /**Fetch Items Range**/
   private  $mailBoxFetchItemsRange;

   /**Select Items**/
   private $mailBoxSelectItems;

   /**fetchItemsLimitRaw - Raw Limit value from call script**/
   private $fetchItemsLimitRaw;

   /**fetchItemsOffsets - Raw non procces offset supplied data **/
   private $fetchItemsOffsetRaw;

   /**Holds output data for  data which requires to return data after proccessing but do to method chaining its not possible **/
   private $outputData;


   /**
    * generate connection string , The connection string is widely used in the libary because , any reference to the mailbox uses the connection string as the mailbox
    * @param   $mailboxName The Mailbox Name we want to generate the connection string for 
    * @return   A string of the connection string
    **/
   private function generateConnString($mailBoxName=null)
   {
        //if mailbox is empty , lets us eexisting one 
        if(empty($mailBoxName)){
            $mailBoxName = $this->mailbox_name;
        }
    
    //connString
    $connString = '{'.$this->host.':'.$this->port.'/imap'.$this->noValidateSSL.'}'.$mailBoxName;

    return $connString;
   }//end method 


   /**
    * formateMailBoxName
    * This is an alias of generateConnString, just that it will look wiered when we use it in certain places 
    * @param   $mailboxName The Mailbox Name we want to generate the connection string for 
    * @return   A string of the connection string
    **/
   private function formatMailBoxName($mailBoxName=null)
   {
    return $this->generateConnString($mailBoxName);
   }//end method 

    /**
     * Connect Server 
     * This method connects the imap server 
     * @param   $paramname descriptionAccepts $param as array
     * $forceNew : This means it should initiate a fresh connection instead of using an existing connection
     * @return class instance to support chaining of methods 
     **/
    public function connectServer($param)
    {

        //if $forceNew is true 
        if(!empty($this->imapConn)){
          return $this;
        }//end if 
      
        //lets assign the params to the various properties respectively 
        $this->host = $param["host"];
     
        $this->port = $param["port"];

        //get mailbox name or set default to inbox 
        $this->mailBoxName = empty($param["mailbox_name"]) ? 'INBOX' : $param["mailbox_name"];

        $this->imapUsername = $param["imap_username"];

        $this->imapPassword = $param["password"];

        //set default or get value if available
        $this->enableSSL = (empty($param["enableSSL"]) || $this->port == 993) ? true : false;

        //Imap Retries 
        $this->connRetries = @$param["imap_retries"];


        //lets now get the ssl suffix 
        if($this->enableSSL == true){
            $this->noValidateSSL = "/ssl/novalidate-cert";
        }else{
            $this->noValidateSSL = "";
        }//end if 
        
        //lets now create our connection strin
        $this->connString = $this->generateConnString($this->mailBoxName);
        
        try{
            //connect to Imap Server 
            $this->imapConn = imap_open($this->connString,$this->imapUsername,$this->imapPassword,null,$this->connRetries);

            //return instance 
            return $this;
        }catch(Exception $e){

            //if error throw an error too 
            throw new Exception("Connection to Imap Server Failed"."-".imap_last_error());
            
            //kill proccessing 
            die();
        }
        
    }//end method 
    
     /**
      * check Imap Connection
      * @param null
      * @return  null
      **/
     private function checkConn()
     {
      
        //if connection string is empty, lets send error and kill script 
        if(empty($this->imapConn)){
            throw new Exception("Imap Server Not Connected, Please Connect Imap Server using this connectServer(param) Method.");
            die();
        }//end if 

        return true;
     }//end check imap connection
      

    /**
     * getMailBoxes
     * This will fetch the mailboxes using the current connection
     * @param   $pattern The Pattern in which the mail box should be retrieved, example * or %,Default pattern is * , more info here : http://php.net/manual/en/function.imap-getmailboxes.php
     * @return   and array will be returned if we were able to fetch some data , else false will return on an empty data 
     **/
    public function getMailBoxes($pattern="*")
    {
     
        //check Conn
        $this->checkConn();
        
        //lets get the list of mail boxes 
        $fetchMailBoxes = imap_getmailboxes($this->imapConn,'{'.$this->host.'}',$pattern);

        //if not success lets send alert 
        if(!is_array($fetchMailBoxes)){
            throw new Exception("Failed to fetch mailboxes - ".imap_last_error());
        }//end if not success 
        
        //sort data 
        krsort($fetchMailBoxes);

        //proccess Data 
        $proccessedData = [];

        //lets proccess the data 
        foreach($fetchMailBoxes AS $mailboxInfoObj){
         
            //lets get  titles 
            $mailboxURI = $mailboxInfoObj->name;
        
            //split the } to get the real name
            $explodeMailBoxURI = explode("}",$mailboxURI);
            
            //get the name,lets now decode the name
            $mailBoxTitle = $explodeMailBoxURI[1];

            $mailBoxTitle = imap_utf7_decode($mailBoxTitle);

            //repackage data into array 
            $proccessedData[] = [
             'title' => $mailBoxTitle,
             'uri_name' => $mailboxURI
            ];
        }//end foreach 

        //return the proccessed data 
        return $proccessedData;
    }//end method


    /**
     * Reconnect Server , This Reconnect the IMAP Server Using existing details 
     * @param   $mailBoxName or null, if $mailBoxName is supplied , we will use it, else we will use the existing one 
     * @return   $this
     **/
    public function reConnectServer($mailBoxName=null)
    {

        //to reconnect server, there must be an existing connection 
        $this->checkConn();

        //set the new mailbox name 
        $this->mailBoxName = !empty($mailBoxName) ? $mailBoxName : $this->mailBoxName;

        //lets now create our connection strin
        $this->connString = $this->generateConnString($this->mailBoxName);
        
        try{

            //connect to Imap Server 
            $this->imapConn = imap_reopen($this->connString,$this->imapUsername,$this->imapPassword);

            //return instance 
            return $this;
        }catch(Exception $e){

            //if error throw an error too 
            throw new Exception("Imap reConnection to Imap Server Failed"."-".var_dump(imap_errors()));
            
            //kill proccessing 
            die();
        }
    }//end reconnect server 


    /**
     * Switch MailBox , Changing the current mailbox location to the supplied one 
     * @param   $mailBoxName  the name of the mailbox , example : INBOX or Draft
     * @return   returns $this to help chaining of methods 
     **/
    public function switchMailBox($mailBoxName)
    {

        //if the mail box has been switched already ,then ignore operations 
        if($this->mailBoxName == $mailBoxName){
            return $this;
        }//end if 

        //lets now reopen the mail server with a different mailbox name 
        $this->reConnectServer($mailBoxName);

        //return this 
        return $this;
    }//end switch mailbox 


    /**
     * getMailBoxInfo - This uses imap_check() to get the current mailbox info 
     * @param  none
     * @return  void
     **/
    public function getMailBoxInfo($forceNew=false,$options=SA_ALL)
    {
        
        //lets check if the mailbox info exists already,we will return it
        if(!empty($this->mailBoxInfo[$this->mailBoxName]) && $forceNew == false){
            return $this->mailBoxInfo[$this->mailBoxName];
        }//end 

        $mailboxInfo = imap_status($this->imapConn,$this->connString,SA_ALL);

        //fetch data 
        if($mailboxInfo){
         
            //lets add a little flavour 
            $mailboxInfo->total_messages = $mailboxInfo->messages;

            //save it in an obj 
            $this->mailBoxInfo[$this->mailBoxName] = $mailboxInfo;

            return $mailboxInfo;
        }else{
            throw new Exception("Failed to fetch mailbox info".imap_last_error());
        }//end if 
            

    }//end getMailBoxInfo 


    /**
     * Get Total Message count of a mail box 
     * @param  
     * @return   integer
     */
    public function getTotalMessages()
    {
        //get mailbox info 
        $mailBoxInfo = $this->getMailBoxInfo();

        return $mailBoxInfo->messages;
    }//end  
    

    /**
     * Fetch MailBox Contents
     *@param  $mailBoxName , the name of the mailbox , example : INBOX or Draft
     * @return   An array containing proccessed mailbox contents 
    **/
    public function fetchMailBoxItems($mailBoxName=null)
    {
        
        //check if there is an active con 
        $this->checkConn();

        //if mailbox name is not supplied , use default mailbox opened 
        if(empty($mailBoxName)){
            $mailBoxName = $this->mailBoxName;
        }///end if 
        
        //lets now switch mailbox
        $this->switchMailBox($mailBoxName);

        //mailbox info 
        $mailboxInfo = $this->getMailBoxInfo();

        $totalMsgs = $mailboxInfo->total_messages;

        //lets check if the mainbox has data , if no data, we will send an empty array 
        if($totalMsgs == 0){

             //lets set the results to empty array 
            $this->setResult([]);

            //return empty array 
            return $this;
        }//end 
            
        //both select and range cannot be used together or our script will go kukuu..confused,user must state it 
        if(sizeof($this->mailBoxSelectItems) > 0 && sizeof($this->mailBoxFetchItemsRange) > 0){

             //send error since these two cannot be used together
             throw new Exception("The methods select and range cannot be used togther, only one can be used.");
             die();
        }//end if 


        //lets check if we have data range or user specified uids to select 
        if(!empty($this->mailBoxSelectItems)){
           
            //lets get the sequences 
           $fetchSequenceArray = $this->mailBoxSelectItems;
           
           //implode delimiter
           $implodeDelimiter = ",";

        }else{
        
            //if we have the range set ,lets get it
            $fetchSequenceArray = $this->mailBoxFetchItemsRange;

            //lets fix range 
            //$fetchSequenceArray = array_walk()

            //implode delimiter
            $implodeDelimiter = ":";
        }//end fetch sequence data 
        
        //prepare sequence
        $fetchSequence = implode($implodeDelimiter,$fetchSequenceArray);

        //lets now fetch the mail data 
        $mailBoxItems =  imap_fetch_overview($this->imapConn,$fetchSequence,0);

        if($mailBoxItems){
            
            //sort 
            if($this->sortDirection == "desc"){
                //array_reverse 
               $mailBoxItems = array_reverse($mailBoxItems);
            }//end if 

            //lets set the results 
            $this->setResult($mailBoxItems);

            //lets return this
            return $this;
        }else{
            throw new Exception("fetchMailBoxItems Error : ".imap_last_error());
        }//end if 
        
        }//end fetch mailboxitems


    /**
     * Set Results - This Sets the results after proccessing a method which has an output
     * @param   $output output of the proccessed data 
     * @return   void;
     **/
    private function setResult($outputData)
    {
        //set out put data 
        $this->outputData = $outputData;
    }


    /**
     * getResults - exposes the output data of methods which sends output after proccesing because due to chaining they cant return data directly
     * @returns $this->outputData
     **/
    public function getResults(){
      //return results 
      return $this->outputData;
    }//end get Results 


    /**
     * orderById
     * @param   $direction , The direction of the sort , that is by ASC 
     * (Ascending Order) or DESC (Descending Order), Note only two 
     * keywords ASC or DESC 
     * Note: This works with only the range method.
     * @return  Method Returns $this for chaining of methods 
     **/
    public function oderById($direction)
    {        
        //sert the direction 
        $this->sortDirection = strtolower($direction);

        //reproccess limit and offset
        $this->convertLimitToRange();

        return $this;
    }//end sort


    /**
     * range - Used in conjunction with fetchMailboxItems to fetch mail box data in range , This is used to implement pagination
     * @param   $from An Integer  uid used for starting the data fetch range
     * @param   $to An Integer  uid used for ending the data fetch range 
     * @return  $this is returned 
     * Note: This method cannot be used with the select method , where a list of uids will be provided 
     **/
    public function range($from,$to)
    {

        //total messages in mailbox 
        $totalMessages = $this->getTotalMessages();

        //lets replace the place holders 
        $from =  str_ireplace(["{{MAX}}","{{MIN}}"],[$totalMessages,1],$from);
        
        //replace
        $to =  str_ireplace(["{{MAX}}","{{MIN}}"],[$totalMessages,1],$to);

        //check and set from 
        if($from < 1){
            $from = 1;
        }
        
        //no arg must be bigger than the total msg num
        if($to > $totalMessages){

            //reset the $to to total messages 
            $to = $totalMessages;
        }//end if 
         

        //lets set the fetchMBItemsRange 
        $this->mailBoxFetchItemsRange = compact('from','to');

        return $this;
    }//end if 


    /**
     * Limit method , This is more like the mysql LIMIT 
     * Here We will do the calclation based on the value provided , unlike the range method where you know 
     * the uid of the two messages you want to pull 
     * @param   $limit an integer for telling the library to limit the data fetched 
     * @param   $offset The limit offset
     * @return   $this instance is returned 
     **/
    public function limit($limit,$offset=0)
    {   
        
        //total messages in mailbox 
        $totalMessages = $this->getTotalMessages();


        //lets replace the place holders 
        $limit =  str_ireplace(["{{MAX}}","{{MIN}}"],[$totalMessages,1],$limit);
        
        //replace
        $offset =  str_ireplace(["{{MAX}}","{{MIN}}"],[$totalMessages,1],$offset);


        //set the limit and offset
        ////the limit will have -1 cos php imap start listing data from 0 index
        $this->fetchItemsLimitRaw = $limit - 1;

        $this->fetchItemsOffsetRaw = $offset;

        //proccess data 
        $this->convertLimitToRange();

        return $this;
    }//end method

    
    //proccess limit method 
    private function convertLimitToRange(){

        //total messages in mailbox 
        $totalMessages = $this->getTotalMessages();
    
        //lets get the sort direction 
        $sortDirection = $this->sortDirection;
    
        //using sort direction lets detect how we will proccess the limits 
        if($sortDirection == "asc"){
        
            //lets proccess the data 
            $offset = 1 + $this->fetchItemsOffsetRaw;

            //offset 
            $limit = $offset + $this->fetchItemsLimitRaw;
    
    }else{//else fetch in descending order 
        
        //offset
        $offset = $totalMessages - $this->fetchItemsOffsetRaw;
         
        //limit        
        $limit = $offset - $this->fetchItemsLimitRaw;
    }//end 
     
    //if its 0 or less , we make it 1 
    $offset = ($offset <= 0) ? 1 : $offset;

    $limit = ($limit <= 0) ? 1 : $limit;


    //check if max than total data 
    $offset = ($offset > $totalMessages) ? $totalMessages : $offset;

    $limit = ($limit > $totalMessages) ? $totalMessages : $limit;
    
    //lets now replace the range values 
    $this->mailBoxFetchItemsRange = ["from" => $offset,"to" => $limit];

    }//end proccess limit

    
    /**
    * Paginate , This is used for paginating inbox data list 
    * @param   $args1 ,$arg2 , $arg3 .... the uids of the message header you want to fetch
    **/

    public function select(){

        //get args
        $args = func_get_args();

        //remove empty data 
        $args = array_filter($args);
        
        //this method must have  args
        if(sizeof($args) == 0){
            throw new Exception("The select method needs at least 1 argument to work");
            die();
        }//end if empty 

        //total messages in mailbox 
        $totalMessages = $this->getTotalMessages();
        
        //a little validation
        foreach($args AS $argtKey => $argValue){

            //argument must be numeric
            if(!is_integer($argValue) || $argValue <= 0){
                throw new Exception("Select Error: Argument $argtKey ($argValue) must be an integer and greater than 0");
            }//end if not integer

            //no arg must be bigger than the total msg num
            if($argValue > $totalMessages){
                throw new Exception("Select Error: Argument $argtKey ($argValue) cannot be greater than the highest mailbox UID ($totalMessages)");
            }//end if 

        }//end loop 
        
        //lets asign the args
        $this->mailBoxSelectItems = $args;

        return $this;
    }//end select


    /**
     * Expunge - Deletes all the messages marked for deletion aafter running moveMail , deleteMail 
     * @return $this 
     **/
    public function expunge()
    {
        //check if there is an active con 
        $this->checkConn();

        //run method 
        $expunge =  imap_expunge($this->imapConn);
        
        return $this;
    }//end method 


    /**
     * MoveMail - Move mail from one mailbox to another 
     * @param   $mailId array of mail UID , Note We only accept mail UID and not MSG ID 
     * @param   $sourceMailBoxName The Current MailBox / source mailbox will the message is moved from
     * @param   $destinationMailBoxName - The Destination or new mail box name the email will be moved to.. Example : Spam , Trash
     * @param   $expunge  an optional argument which will run the expunge() method to delete the mail at the source folder since it will be mared for removal after the mail move 
     * @return $this
     **/
    public function moveMail($mailsIdArray,$sourceMailBox,$destinationMailBoxName,$expunge=false)
    {
        
        //if the source mailbox is same as the current(destination),jus return true 
        if($sourceMailBox == $destinationMailBoxName){
            return true;
        }//end if 

        //check if there is an active con 
        $this->checkConn();

        //switch mail box 
        $this->switchMailBox($sourceMailBox);

        //do start proccessing 
        foreach($mailsIdArray AS $key => $mailId){
            
            //(int) $mailId
            $mailId = (int) $mailId;

            //if not int and bigger than 0, lets send an exception 
            if(!is_integer($mailId) || $mailId <= 0){
                throw new Exception("Inavlid mail uid at index $key");
                die();
            }//end method 
        }//end loop 
        
        //messages uid 
        $mailUID = implode(",",$mailsIdArray);


        //mail move 
        $moveMail = imap_mail_move($this->imapConn,$mailUID,$destinationMailBoxName,CP_UID);
        

        //if errors lets keep the after we will send it to the user
        if(!$moveMail){
           throw new Exception("Mail Failed to Move : ".imap_last_error());
        }//end if 
        

        //if expunge is true then lets run it 
        if($expunge == true){
          $this->expunge();
        }//end if 

        //return instance of class 
        return true;
    }//end method 

    
    /**
     * Close Imap Connection 
     **/
    public function close()
    {
          //if connection exists close it 
        if(!empty($this->imapConn)){
            imap_close($this->imapConn);
        }//end close imap connection 

    }//end close connection

    /**
    *Destructor, Its will auto close the imap connection if not manually closed 
    *@param none
    * @return   void
     */
    public function __destruct()
    {
     //close the connection 
     $this->close();
    }//end 


}//end imap class 