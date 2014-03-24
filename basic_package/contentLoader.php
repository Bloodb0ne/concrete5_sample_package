<?php 
	
	class ContentJsonLoader
	{
		var $package = false;
		var $pages = array();
	
	public function __construct($pkg=false){
		$this->package = $pkg;
	}

	public function loadAttributes(){
		$attrList = file_get_contents("attributes.json",true);
		$attrList = json_decode($attrList,true);

		foreach ($attrList as $attribute) {
			$this->createAttribute($attribute);
		}

	}

	public function loadComposer(){
		$compList = file_get_contents("composer.json",true);
		$compList = json_decode($compList,true);

		foreach ($compList as $pageType => $pageTypeSettings) {
			$this->setPageTypeComposer($pageType,$pageTypeSettings);
		}

	}
	private function setPageTypeComposer($type,$settings){
		$ct = CollectionType::getByHandle($type);
		
		if(isset($settings['publishTarget']))
		{
			$target = $settings['publishTarget'];

			if(isset($settings['publishValue']))
				$value  = $settings['publishValue'];
			else 
				$value = "";
			
			switch ($target) {
			case 'all':
				$ct->saveComposerPublishTargetAll();
				break;
			case 'pageType':
			case 'pagetype':
				$ctParents = CollectionType::getByHandle($value);
				if(is_object($ctParents)){
					$ct->saveComposerPublishTargetPageType($ctParents);
				}
				break;
			case 'targetPage':
			case 'targetpage':
				//Chech if we added the pages
				if(isset($this->pages[$value]) && is_object($this->pages[$value]))
				{
					$ct->saveComposerPublishTargetPage($this->pages[$value]);
				}
				break;
			
			default:
				// Error report? but not do anything
				break;
			}
		}
		
		//Array with the composer attributes
		$akArray = array();

		if(isset($settings['defaultAttributes']) && is_array($settings['defaultAttributes'])){

			foreach ($settings['defaultAttributes'] as $attrHandle) {
				//Get Attribute
				$ak = CollectionAttributeKey::getByHandle($attrHandle);
				$ct->assignCollectionAttribute($ak);
				$akArray[]  = $ak->getAttributeKeyID();
			}
		}

		//Save the attributes for the default composer
		$ct->saveComposerAttributeKeys($akArray);

		return $ct;
	}
	public function loadPages(){
		$pageList = file_get_contents("pages.json",true);
		$pageList = json_decode($pageList,true);

		return $this->createPageRecur($pageList);
	}


	private function createPageRecur($pagelist,$parent=false){
		foreach ($pagelist as $page) {
			$innerParent = $this->createPage($page,$parent);

			//Create children 
			if(!$innerParent) return false;

			if(isset($page['children'])){
				$this->createPageRecur($page['children'],$innerParent);
			}
		}

	}
	

	private function addArea($page,$name,$content){
		$area  = Area::getOrCreate($page,$name);

		$block = BlockType::getByHandle('html');
		$data = array(
		    'content' => $content
		);
		$page->addBlock($block,$name, $data);
	}

	private function createPage($page,$parent=false){
		//Get Page type
		$ct = CollectionType::getByHandle($page['type']);

		if(!$ct) return false;

		$root = new stdClass();

		if($parent == false){
			$root = Page::getById(HOME_CID);
		}else{
			$root = $parent;
		}


		if($root){

			$data = array();
			if($this->package)
			{
				$data = array(
					'cName' => $page['name'],
					'cDescription' => $page['desc'],
					'pkgID' => $this->package->getPackageID());
			}else
			{
				$data = array(
					'cName' => $page['name'],
					'cDescription' => $page['desc']);

			}

			$newPage = $root->add($ct,$data);


			//Add Attributes
			if(isset($page['attributes']) && is_array($page['attributes']))
			foreach ($page['attributes'] as $attrKey => $attrVal) {
				$newPage->addAttribute($attrKey,$attrVal);
			}

			if(isset($page['areas']) && is_array($page['areas']))
			foreach ($page['areas'] as $areaName => $areaContent) {
				$this->addArea($newPage,$areaName,$areaContent);
			}

			// if(isset($page['globalAreas']) && is_array($page['globalAreas']))
			// foreach ($page['globalAreas'] as $attrKey => $attrVal) {
			// 	$newPage->addArea($attrKey,$attrVal);
			// }

			//Add pages for the Composer construction
			if(isset($page['id']) && is_int($page['id']))
			{
				array_push($this->pages,array($page['id'] => $newPage));
			}
			return $newPage;
		}

		return false;
	}

	public function removePages(){
		$pkgId = $this->package->getPackageId();
		$root = Page::getById(HOME_CID);
		$children = $root->getCollectionChildrenArray();
		foreach ($children as $page) {
			$child = Page::getById($page);
			if($child->getPackageID() == $pkgId){
				$child->delete();
			}
		}
	}
	public function addTheme($themeName){
		$theme = PageTheme::add($themeName, $this->package);
		$theme->applyToSite();

		$this->createPageTypes($themeName);
	}

	private function createPageTypes($themeName){
		//TODO: Refactor with getFilesInTheme();

		if ($handle = opendir(dirname(__FILE__).'\themes\\'.$themeName)) {
        while (false !== ($entry = readdir($handle))) {
			
            if ($entry != "." &&
            	$entry != ".." &&
            	!preg_match("/(view|default).php/i",$entry) &&
				preg_match("/.php/i",$entry)) {

                $this->addPageType(str_replace(".php","",$entry));
            }
        }
        closedir($handle);
   		}
	}

	private function addPageType($handle){
         $tp  = CollectionType::getByHandle($handle);
         if(!$tp){

         	$name = ucwords(str_replace("_"," ",$handle));
         	$tp = CollectionType::add(
         		array(
         		'ctHandle' => $handle,
         		'ctName' => $name),
         		$this->package);
         }
	}

	public function loadBlock($handle){
		BlockType::installBlockTypeFromPackage($handle,$this->package);
	}

	public function loadAttributeTypes(){
		//Get All attribute types
		$path = dirname(__FILE__).'\models\attribute\types';
		if ($handle = opendir($path)) {
        while (false !== ($entry = readdir($handle))) {
			
            if ($entry != "." &&
            	$entry != ".." &&
            	is_dir($path."\\".$entry)) {

	            $multiFileAttrType = AttributeType::getByHandle($entry);
				if(!is_object($multiFileAttrType) || !intval($multiFileAttrType->getAttributeTypeID()) ) { 
					$name = ucwords(str_replace("_"," ",$entry));
					$multiFileAttrType = AttributeType::add($entry, t($name), $this->package); 			  
				}
				
				$db = Loader::db(); 

				$collectionAttrCategory = AttributeKeyCategory::getByHandle('collection');
				
				$catTypeExists = $db->getOne('SELECT count(*) FROM AttributeTypeCategories WHERE atID=? AND akCategoryID=?', array( $multiFileAttrType->getAttributeTypeID(), $collectionAttrCategory->getAttributeKeyCategoryID() ));
				if(!$catTypeExists) $collectionAttrCategory->associateAttributeKeyType($multiFileAttrType);	


            }
        }
        closedir($handle);
   		}
	}

	public function loadBlocks(){
		//Get All blocks
		$path = dirname(__FILE__).'\blocks';
		if ($handle = opendir($path)) {
        while (false !== ($entry = readdir($handle))) {
			
            if ($entry != "." &&
            	$entry != ".." &&
            	is_dir($path."\\".$entry)) {

                BlockType::installBlockTypeFromPackage($entry,$this->package);
            }
        }
        closedir($handle);
   		}
	}
	private function createAttribute($attr){

		if(!isset($attr['searchable'])) $attr['searchable'] = true;
		if(!isset($attr['checked'])) $attr['checked'] = true;
		if(!isset($attr['selectMultiple'])) $attr['selectMultiple'] = false;
		if(!isset($attr['selectOrder'])) $attr['selectOrder'] = 'display_asc';

		$ak = CollectionAttributeKey::getByHandle($attr['handle']);
		//Check if exists
		if(!$ak){
			//Create Attribute
			$ak = CollectionAttributeKey::add(
				AttributeType::getByHandle($attr['type']),
				array(
					'akHandle'=>$attr['handle'],
					'akName'=>$attr['name'],
					'akIsSearchable' => $attr['searchable'],
					'akCheckByDefault' => $attr['checked']),
				$this->package);

		if(isset($attr['type']) && $attr['type'] == 'select'){
			//Add extended options
			$db = Loader::db();

			$db->Replace('atSelectSettings', array(
				        'akID' => $ak->getAttributeKeyID(),
				        'akSelectAllowMultipleValues' => $attr['selectMultiple'],
				        'akSelectOptionDisplayOrder' => $attr['selectOrder']
				      ), array('akID'), true);

			if(!isset($attr['selectOptions'])) $attr['selectOptions'] = array();

			$newOptionSet = new SelectAttributeTypeOptionList();
		    $displayOrder = 0;

		    foreach ($attr['selectOptions'] as $option) {
		      $opt = SelectAttributeTypeOption::getByValue($option, $ak);

		      if (!$opt) {
		        $opt = SelectAttributeTypeOption::add($ak,$option);
		      }

		      if ($attr['selectOrder'] == 'display_asc') {
		        $opt->setDisplayOrder($displayOrder);
		      }
		      
		      $newOptionSet->add($opt);
		      $displayOrder++;
		    }
		}

			return $ak;
		}

		return $ak;
	}	
	}
	
 ?>