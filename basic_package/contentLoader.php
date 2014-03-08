<?php 
	
	class ContentJsonLoader
	{
		var $package = false;
	
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