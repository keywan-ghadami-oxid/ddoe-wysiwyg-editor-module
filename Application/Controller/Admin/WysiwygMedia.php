<?php
/**
 * This file is part of OXID eSales WYSIWYG module.
 *
 * OXID eSales WYSIWYG module is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eSales WYSIWYG module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eSales WYSIWYG module.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2017
 * @version   OXID eSales WYSIWYG
 */

namespace OxidEsales\WysiwygModule\Application\Controller\Admin;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\WysiwygModule\Application\Model\Media;

use OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController;
use OxidEsales\Eshop\Core\DatabaseProvider;

/**
 * Class WysiwygMedia
 */
class WysiwygMedia extends AdminDetailsController
{

    /**
     * Current class template name.
     *
     * @var string
     */
    protected $_sThisTemplate = 'dialog/ddoewysiwygmedia.tpl';

    /**
     * @var Media
     */
    protected $_oMedia = null;

    protected $_sUploadDir = '';
    protected $_sThumbDir = '';
    protected $_iDefaultThumbnailSize = 0;
    protected $_sFolderId = '';


    /**
     * Overrides oxAdminDetails::init()
     */
    public function init()
    {
        parent::init();

        if ( $this->_oMedia === null )
        {
            if( ( $sId = $this->getConfig()->getRequestParameter( 'folderid' ) ) )
            {
                $this->_sFolderId = $sId;

                //$this->_oMedia->setFolderNameForFolderId( $sId );
            }

            $oModule = oxNew(\OxidEsales\Eshop\Core\Module\Module::class);

            if ( class_exists( '\\OxidEsales\\VisualCmsModule\\Application\\Model\\Media' ) && $oModule->load( 'ddoevisualcms' ) && $oModule->isActive() )
            {
                $this->_oMedia = oxNew( \OxidEsales\VisualCmsModule\Application\Model\Media::class );
            }
            else
            {
                $this->_oMedia = oxNew( Media::class );
            }
            $this->_oMedia->init( null, false, $this->_sFolderId  );
        }

        $this->_sUploadDir = $this->_oMedia->getMediaPath();
        $this->_sThumbDir  = $this->_oMedia->getThumbnailPath();
        $this->_iDefaultThumbnailSize = $this->_oMedia->getDefaultThumbSize();
    }

    /**
     * Overrides oxAdminDetails::render
     *
     * @return string
     */
    public function render()
    {
        $oConfig = \OxidEsales\Eshop\Core\Registry::getConfig();
        $iShopId = $oConfig->getConfigParam('blMediaLibraryMultiShopCapability') ? $oConfig->getActiveShop()->getShopId() : null;

        $this->_aViewData['aFiles'] = $this->_getFiles(0, $iShopId);
        $this->_aViewData['iFileCount'] = $this->_getFileCount($iShopId);
        $this->_aViewData['sResourceUrl'] = $this->_oMedia->getMediaUrl();
        $this->_aViewData['sThumbsUrl'] = $this->_oMedia->getThumbnailUrl();
        $this->_aViewData[ 'sFoldername' ]  = $this->_oMedia->getFolderName();
        $this->_aViewData[ 'sFolderId' ]    = $this->_sFolderId;
        $this->_aViewData[ 'sTab' ]         = $this->getConfig()->getRequestParameter( 'tab' );

        return parent::render();
    }

    /**
     * @param int  $iStart
     * @param null $iShopId
     *
     * @return array
     */
    protected function _getFiles($iStart = 0, $iShopId = null)
    {
        $oDb = DatabaseProvider::getDb( DatabaseProvider::FETCH_MODE_ASSOC );

        $sSelect = "SELECT * FROM `ddmedia` WHERE 1 " .
                   ( $iShopId != null ? "AND `OXSHOPID` = " . $oDb->quote( $iShopId ) . " " : "" ) .
                   "AND `DDFOLDERID` = " . $oDb->quote( $this->_sFolderId ) . " " .
                   "ORDER BY `OXTIMESTAMP` DESC LIMIT " . $iStart . ", 18 ";

        return $oDb->getAll($sSelect);
    }

    /**
     * @param null $iShopId
     *
     * @return false|string
     */
    protected function _getFileCount($iShopId = null)
    {
        $oDb = DatabaseProvider::getDb( DatabaseProvider::FETCH_MODE_ASSOC );

        $sSelect = "SELECT COUNT(*) AS 'count' FROM `ddmedia` WHERE 1 " .
                   ( $iShopId != null ? "AND `OXSHOPID` = " . $oDb->quote( $iShopId ) . " " : "" ) .
                   "AND `DDFOLDERID` = " . $oDb->quote( $this->_sFolderId );

        return $oDb->getOne($sSelect);
    }

    /**
     * Upload files
     */
    public function upload()
    {
        $oConfig = \OxidEsales\Eshop\Core\Registry::getConfig();

        $sId = null;

        try
        {
            if ($_FILES)
            {
                $this->_oMedia->createDirs();

                $sFileSize = $_FILES['file']['size'];
                $sFileType = $_FILES['file']['type'];

                $sSourcePath = $_FILES['file']['tmp_name'];
                $sDestPath = $this->_sUploadDir . $_FILES['file']['name'];

                $aFile = $this->_oMedia->uploadeMedia($sSourcePath, $sDestPath, true);

                $sId = md5( $aFile[ 'filename' ] . $this->_sFolderId );
                $sThumbName = $aFile[ 'thumbnail' ];
                $sFileName = $aFile[ 'filename' ];

                $aImageSize = null;
                $sImageSize = '';

                if (is_readable($sDestPath) && preg_match("/image\//", $sFileType)) {
                    $aImageSize = getimagesize($sDestPath);
                    $sImageSize = ($aImageSize ? $aImageSize[0] . 'x' . $aImageSize[1] : '');
                }

                $oDb = DatabaseProvider::getDb();
                $iShopId = $oConfig->getActiveShop()->getShopId();

                $sInsert = "REPLACE INTO `ddmedia`
                              ( `OXID`, `OXSHOPID`, `DDFILENAME`, `DDFILESIZE`, `DDFILETYPE`, `DDTHUMB`, `DDIMAGESIZE`, `DDFOLDERID` )
                            VALUES
                              ( ?, ?, ?, ?, ?, ?, ?, ? );";

                $oDb->execute(
                    $sInsert,
                    array(
                        $sId,
                        $iShopId,
                        $sFileName,
                        $sFileSize,
                        $sFileType,
                        $sThumbName,
                        $sImageSize,
                        $this->_sFolderId
                    )
                );
            }

            if ($oConfig->getRequestParameter('src') == 'fallback') {
                $this->fallback(true);
            } else {
                header('Content-Type: application/json');
                die( json_encode(
                    array(
                        'success'   => true,
                        'id'        => $sId,
                        'file'      => $sFileName,
                        'filepath'  => $sDestPath,
                        'filetype'  => $sFileType,
                        'filesize'  => $sFileSize,
                        'imagesize' => $sImageSize,
                    )
                ) );
            }
        }
        catch( \Exception $e )
        {
            if ($oConfig->getRequestParameter('src') == 'fallback')
            {
                $this->fallback( false, true );
            }
            else
            {
                die( json_encode(
                    array(
                        'success'   => false,
                        'id'        => $sId,
                    )
                ) );
            }
        }
    }

    /**
     * @param bool $blComplete
     * @param bool $blError
     */
    public function fallback($blComplete = false, $blError = false)
    {
        $oViewConf = $this->getViewConfig();

        $sFormHTML = '<html><head></head><body style="text-align:center;">
                          <form action="' . $oViewConf->getSelfLink() . 'cl=ddoewysiwygmedia_view&fnc=upload&src=fallback" method="post" enctype="multipart/form-data">
                              <input type="file" name="file" onchange="this.form.submit();" />
                          </form>';

        if ($blComplete) {
            $sFormHTML .= '<script>window.parent.MediaLibrary.refreshMedia();</script>';
        }

        $sFormHTML .= '</body></html>';

        header('Content-Type: text/html');
        die($sFormHTML);
    }

    public function addFolder()
    {
        $oConfig = $this->getConfig();

        $sId = null;

        if ( ( $sName = $oConfig->getRequestParameter( 'name' ) ) )
        {
            $sParentPath = $oConfig->getRequestParameter( 'path' ) ? $oConfig->getRequestParameter( 'path' ) : null;

            $sPath = Registry::getConfig()->getPictureDir( false ) . $sParentPath;
            $sMediaRoot = $this->_oMedia->getRootMediaPath();
            $sParentPath = str_replace( $sMediaRoot, '', $sPath );

            $this->_oMedia->createDirs();

            $sName = $this->_oMedia->createCustomDir( $sName, $sParentPath );

            $sDestPath = $this->_sUploadDir . ( $sParentPath ? $sParentPath . '/' : '' ) . $sName;

            $oDb = DatabaseProvider::getDb();

            $iShopId = $oConfig->getActiveShop()->getShopId();

            $sId = md5( ( $sParentPath ? $sParentPath . '/' : '' ) . $sName );

            $sInsert = "REPLACE INTO `ddmedia`
                          ( `OXID`, `OXSHOPID`, `DDFILENAME`, `DDFILESIZE`, `DDFILETYPE`, `DDTHUMB`, `DDIMAGESIZE` )
                        VALUES
                          ( '" . $sId . "', '" . $iShopId . "', " . $oDb->quote( $sName ) . ", 0, " . $oDb->quote( 'directory' ) . ", '', '' );";

            $oDb->execute( $sInsert );

            header( 'Content-Type: application/json' );
            die( json_encode( array( 'success' => true, 'id' => $sId, 'file' => $sName, 'filepath' => $sDestPath, 'filetype' => 'directory', 'filesize' => 0, 'imagesize' => '' ) ) );
        }
        else
        {
            header( 'Content-Type: application/json' );
            die( json_encode( array( 'success' => false ) ) );
        }

    }

    public function rename()
    {
        $blReturn = false;
        $sMsg = '';

        $oConfig = $this->getConfig();

        $sNewId = $sId = $oConfig->getRequestParameter( 'id' );
        $sOldName = $oConfig->getRequestParameter( 'oldname' );
        $sNewName = $oConfig->getRequestParameter( 'newname' );
        $sFiletype = $oConfig->getRequestParameter( 'filetype' );

        if( $sId && $sOldName && $sNewName )
        {
            $sParentPath = $oConfig->getRequestParameter( 'path' ) ? $oConfig->getRequestParameter( 'path' ) : null;

            $oDb = DatabaseProvider::getDb();

            //check if image is in use before moving it to another place
            $sPath = Registry::getConfig()->getPictureDir( false ) . $sParentPath;
            $sMediaRoot = $this->_oMedia->getRootMediaPath();
            $sPath = str_replace( $sMediaRoot, '', $sPath );
            $blFileInUse = $this->_checkIfFileIsInUse( $sPath . $sOldName );

            if( !$blFileInUse && ( $sNewName = $this->_oMedia->rename( $sOldName, $sNewName, $sParentPath, $sFiletype ) ) )
            {
                $iShopId = $oConfig->getActiveShop()->getShopId();

                $sNewId = md5(( $sParentPath ? $sParentPath . '/' : '' ) . $sNewName );

                $sUpdate = "UPDATE `ddmedia`
                              SET `DDFILENAME` = '$sNewName', `OXID` = '$sNewId' 
                            WHERE `OXID` = '$sId' AND `OXSHOPID` = '$iShopId';";

                $oDb->execute( $sUpdate );

                $sUpdate = "UPDATE `ddmedia`
                              SET `DDFOLDERID` = '$sNewId' 
                            WHERE `DDFOLDERID` = '$sId' AND `OXSHOPID` = '$iShopId';";

                $oDb->execute( $sUpdate );
                $blReturn = true;
            }

            if( $blFileInUse )
            {
                $sMsg = 'DD_MEDIA_RENAME_FILE_ERR';
            }
        }

        header( 'Content-Type: application/json' );
        die( json_encode( array( 'success' => $blReturn, 'msg' => $sMsg, 'name' => $sNewName, 'id' => $sNewId ) ) );
    }

    /**
     * Remove file
     */
    public function remove()
    {
        $oConfig = \OxidEsales\Eshop\Core\Registry::getConfig();

        if ($aIDs = $oConfig->getRequestParameter('id')) {
            $oDb = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);

            $sSelect = "SELECT `OXID`, `DDFILENAME`, `DDTHUMB`, `DDFILETYPE`, `DDFOLDERID` " .
                       "FROM `ddmedia` " .
                       "WHERE `OXID` IN(" . implode( ",", $oDb->quoteArray( $aIDs ) ) . ") " .
                       " OR `DDFOLDERID` IN(" . implode( ",", $oDb->quoteArray( $aIDs ) ) . ") " .
                       "ORDER BY `DDFOLDERID` ASC";
            $aData = $oDb->getAll($sSelect);

            $aFolders = array();
            foreach( $aData as $sKey => $aRow )
            {
                if ( $aRow[ 'DDFILETYPE' ] == 'directory' )
                {
                    $aFolders[ $aRow[ 'OXID' ] ] = $aRow[ 'DDFILENAME' ];
                    unset( $aData[ $sKey ] );
                }
            }

            foreach ($aData as $aRow)
            {
                $sFolderPath = $this->_sUploadDir . ( $aRow[ 'DDFOLDERID' ] ? $aFolders[ $aRow[ 'DDFOLDERID' ] ] . '/' : '' );
                $sThumbPath = ( $aRow[ 'DDFOLDERID' ] ? $sFolderPath . 'thumbs/' : $this->_sThumbDir );

                @unlink( $sFolderPath . $aRow[ 'DDFILENAME' ] );

                if ($aRow['DDTHUMB']) {
                    foreach( glob( $sThumbPath . str_replace( 'thumb_' . $this->_iDefaultThumbnailSize . '.jpg', '*', $aRow[ 'DDTHUMB' ] ) ) as $sThumb )
                    {
                        @unlink($sThumb);
                    }
                }

                $sDelete = "DELETE FROM `ddmedia` WHERE `OXID` = '" . $aRow['OXID'] . "'; ";
                $oDb->execute($sDelete);
            }

            // remove folder
            foreach ( $aFolders as $sOxid => $sFolderName )
            {
                @rmdir( $this->_sUploadDir . $sFolderName . '/thumbs' );
                @rmdir( $this->_sUploadDir . $sFolderName );
                $sDelete = "DELETE FROM `ddmedia` WHERE `OXID` = '" . $sOxid . "'; ";
                $oDb->execute( $sDelete );
            }
        }

        exit();
    }

    public function movefile()
    {
        $blReturn = false;
        $sMsg = '';

        $oConfig = $this->getConfig();

        $sFileID = $oConfig->getRequestParameter( 'sourceid' );
        $sFileName = $oConfig->getRequestParameter( 'file' );
        $sFolderID = $oConfig->getRequestParameter( 'targetid' );
        $sFolderName = $oConfig->getRequestParameter( 'folder' );
        $sThumb = $oConfig->getRequestParameter( 'thumb' );

        if( $sFileID && $sFileName && $sFolderID && $sFolderName )
        {
            $oDb = DatabaseProvider::getDb();

            //check if image is in use before moving it to another place
            $blFileInUse = $this->_checkIfFileIsInUse( $sFileName );

            if( !$blFileInUse && $this->_oMedia->moveFile( $sFileName, $sFolderName, $sThumb ) )
            {
                $iShopId = $oConfig->getActiveShop()->getShopId();

                $sUpdate = "UPDATE `ddmedia`
                              SET `DDFOLDERID` = '$sFolderID'  
                            WHERE `OXID` = '$sFileID' AND `OXSHOPID` = '$iShopId';";

                $oDb->execute( $sUpdate );

                $blReturn = true;
            }

            if( $blFileInUse )
            {
                $sMsg = 'DD_MEDIA_MOVE_FILE_ERR';
            }
        }

        header( 'Content-Type: application/json' );
        die( json_encode( array( 'success' => $blReturn, 'msg' => $sMsg ) ) );
    }

    /**
     * Load more files
     */
    public function moreFiles()
    {
        $oConfig = \OxidEsales\Eshop\Core\Registry::getConfig();
        $iStart = $oConfig->getRequestParameter('start') ? $oConfig->getRequestParameter('start') : 0;
        //$iShopId = $oConfig->getRequestParameter( 'oxshopid' ) ? $oConfig->getRequestParameter( 'oxshopid' ) : null;
        $iShopId = $oConfig->getConfigParam('blMediaLibraryMultiShopCapability') ? $oConfig->getActiveShop()->getShopId() : null;

        $aFiles = $this->_getFiles($iStart, $iShopId);
        $blLoadMore = ($iStart + 18 < $this->_getFileCount($iShopId));

        header('Content-Type: application/json');
        die(json_encode(array('files' => $aFiles, 'more' => $blLoadMore)));
    }

    public function getBreadcrumb()
    {
        $aBreadcrumb = array();

        $oPath = new \stdClass();
        $oPath->active = ($this->_oMedia->getFolderName() ? false : true );
        $oPath->name = 'Root';
        $aBreadcrumb[] = $oPath;

        if( $this->_oMedia->getFolderName() )
        {
            $oPath = new \stdClass();
            $oPath->active = true;
            $oPath->name = $this->_oMedia->getFolderName();
            $aBreadcrumb[] = $oPath;
        }

        return $aBreadcrumb;
    }

    /**
     * @param $sFileName
     *
     * @return mixed
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    protected function _checkIfFileIsInUse( $sFileName )
    {
        $oDb = DatabaseProvider::getDb();

        $sImageUrlPath = $sFileName;
        $aLangs = \OxidEsales\Eshop\Core\Registry::getLang()->getLanguageArray();
        $aWheres = array();
        foreach ( $aLangs as $oLang )
        {
            if ( $oLang->id == 0 )
            {
                $aWheres[] = "`OXCONTENT` LIKE '%$sImageUrlPath%'";
            }
            else
            {
                $aWheres[] = "`OXCONTENT_{$oLang->id}` LIKE '%$sImageUrlPath%'";
            }
        }

        $sSelect = "SELECT COUNT(*) FROM `oxcontents` WHERE " . implode( ' OR ', $aWheres );

        $blFileInUse = $oDb->getOne( $sSelect );

        return $blFileInUse;
    }
}
