<?php 

defined('C5_EXECUTE') or die(_("Access Denied."));

include("contentLoader.php");

class BasicPackagePackage extends Package {

	protected $pkgHandle = 'basic_package';
	protected $appVersionRequired = '5.6';
	protected $pkgVersion = '1.0';
	
	public function getPackageDescription() {
		return t("Sample content loading package");
	}
	
	public function getPackageName() {
		return t("Basic Package");
	}
	public function uninstall(){
		//Optional to remove the pages when removing the package
		// $cnt = new ContentJsonLoader($this);
		// $cnt->removePages();

		parent::uninstall();
	}
	public function install() {
		$pkg = parent::install();	

		$cnt = new ContentJsonLoader($pkg);
		// $cnt->addTheme('basic_theme'); //Installs and activates the theme
		// $cnt->loadAttributes(); //loads all page attributes described in the attributes.json file
		// $cnt->loadPages(); //loads all pages described in the pages.json file
		// $cnt->loadBlocks(); // Loads all the blocks in the /blocks folder
		// $cnt->loadAttributeTypes();
		// $cnt->loadComposer();
	}
}

?>