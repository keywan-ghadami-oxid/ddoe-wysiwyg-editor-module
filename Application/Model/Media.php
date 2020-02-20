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

namespace OxidEsales\WysiwygModule\Application\Model;

use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Model\BaseModel;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class Media
 *
 * @mixin \OxidEsales\Eshop\Core\Model\BaseModel
 */
class Media extends BaseModel
{

    protected $_sMediaPath = '/out/pictures/ddmedia/';
    protected $_iDefaultThumbnailSize = 185;
    protected $_sFolderName;
    protected $_aFileExtBlacklist = [ 'php.*', 'exe', 'js', 'jsp', 'cgi', 'cmf', 'phtml', 'pht', 'phar' ]; // regex allowed


    /**
     * @param null|string $sTableName
     * @param bool        $blForceAllFields
     */
    public function init( $sTableName = NULL, $blForceAllFields = false, $sFolderId = '' )
    {
        if( $sFolderId )
        {
            $this->setFolderNameForFolderId( $sFolderId );
        }
    }

    public function getRootMediaPath( $sFile = '' )
    {
        $sPath = rtrim( getShopBasePath(), '/' ) . $this->_sMediaPath;

        if ( $sFile )
        {
            return $sPath . $sFile;
        }

        return $sPath;
    }

    /**
     * @param string       $sFile
     * @param null|integer $iThumbSize
     *
     * @return bool|string
     */
    public function getThumbnailUrl($sFile = '', $iThumbSize = null)
    {
        if ($sFile) {
            if (!$iThumbSize) {
                $iThumbSize = $this->_iDefaultThumbnailSize;
            }

            $sThumbName = $this->getThumbName($sFile, $iThumbSize);

            if ($sThumbName) {
                return $this->getMediaUrl('thumbs/' . $sThumbName);
            }
        } else {
            return $this->getMediaUrl('thumbs/');
        }

        return false;
    }

    /**
     * @param string       $sFile
     * @param null|integer $iThumbSize
     *
     * @return string
     */
    public function getThumbName($sFile, $iThumbSize = null)
    {
        if (!$iThumbSize) {
            $iThumbSize = $this->_iDefaultThumbnailSize;
        }

        return str_replace('.', '_', md5(basename($sFile))) . '_thumb_' . $iThumbSize . '.jpg';
    }

    /**
     * @param string $sFile
     *
     * @return bool|string
     */
    public function getMediaUrl($sFile = '')
    {
        $oConfig = \OxidEsales\Eshop\Core\Registry::getConfig();

        $sFilePath = $this->getMediaPath($sFile);

        if (!is_readable($sFilePath)) {
            return false;
        }

        if ($oConfig->isSsl()) {
            $sUrl = $oConfig->getSslShopUrl(false);
        } else {
            $sUrl = $oConfig->getShopUrl(false);
        }

        $sUrl = rtrim( $sUrl, '/' ) . $this->_sMediaPath . ( $this->_sFolderName ? $this->_sFolderName . '/' : '' );

        if ($sFile)
        {
            return $sUrl . ( strpos( $sFile, 'thumbs/' ) !== false ? $sFile : basename( $sFile ) );
        }

        return $sUrl;
    }

    /**
     * @param string $sFile
     *
     * @return string
     */
    public function getMediaPath( $sFile = '' )
    {
        $this->_checkAndSetFolderName( $sFile );

        $sPath = $this->getRootMediaPath() . ( $this->_sFolderName ? $this->_sFolderName . '/' : '' );

        if ( $sFile )
        {
            return $sPath . ( strpos( $sFile, 'thumbs/' ) !== false ? $sFile : basename( $sFile ) );
        }

        return $sPath;

    }

    /**
     * @return int
     */
    public function getDefaultThumbSize()
    {
        return $this->_iDefaultThumbnailSize;
    }

    /**
     * @param string $sSourcePath
     * @param string $sDestPath
     * @param bool   $blCreateThumbs
     *
     * @return array
     */
    public function uploadeMedia($sSourcePath, $sDestPath, $blCreateThumbs = false)
    {
        $this->createDirs();

        $sThumbName = '';
        $sDestPath = $this->_checkAndGetFileName( $sDestPath );
        $sFileName = basename($sDestPath);
        $iFileCount = 0;

        if( $this->validateFilename( $sFileName ) )
        {
            while (file_exists($sDestPath)) {
                $aFileParts = explode('.', $sFileName);
                $aFileParts = array_reverse($aFileParts);

                $sFileExt = $aFileParts[0];
                unset($aFileParts[0]);

                $sBaseName = implode('.', array_reverse($aFileParts));

                $aBaseParts = explode('_', $sBaseName);
                $aBaseParts = array_reverse($aBaseParts);

                if (strlen($aBaseParts[0]) == 1 && is_numeric($aBaseParts[0])) {
                    $iFileCount = (int) $aBaseParts[0];
                    unset($aBaseParts[0]);
                }

                $sBaseName = implode('_', array_reverse($aBaseParts));

                $sFileName = $sBaseName . '_' . (++$iFileCount) . '.' . $sFileExt;
                $sDestPath = dirname( $sDestPath ) . '/' . $sFileName;
            }

            move_uploaded_file($sSourcePath, $sDestPath);

            if ($blCreateThumbs) {
                try {
                    $sThumbName = $this->createThumbnail($sFileName);

                    $this->createMoreThumbnails($sFileName);
                } catch ( \Exception $e) {
                    $sThumbName = '';
                }
            }

            return array(
                'filepath'  => $sDestPath,
                'filename'  => $sFileName,
                'thumbnail' => $sThumbName
            );
        }

        return false;
    }

    /**
     * Create directories
     */
    public function createDirs()
    {
        if (!is_dir($this->getMediaPath())) {
            mkdir($this->getMediaPath());
        }

        if (!is_dir($this->getThumbnailPath())) {
            mkdir($this->getThumbnailPath());
        }
    }

    public function createCustomDir( $sName, $sParentPath )
    {
        $this->createDirs();

        $sPath = $this->getMediaPath() . ( $sParentPath ? $sParentPath . '/' : '' );
        $sNewPath = $sPath . $sName;

        $sNewPath = $this->_checkAndGetFolderName( $sNewPath, $sPath );

        if( !is_dir( $sNewPath ) )
        {
            mkdir( $sNewPath );
        }

        return basename( $sNewPath );
    }

    public function rename( $sOldName, $sNewName, $sParentPath, $sType = 'file' )
    {
        if( $sParentPath )
        {
            // sanitize filename
            $sNewName = $this->_sanitizeFilename( $sNewName );

            $sPath = Registry::getConfig()->getPictureDir( false ) . $sParentPath;

            $sOldPath = $sPath . $sOldName;
            $sNewPath = $sPath . $sNewName;

            if( $sType == 'directory' )
            {
                $sNewPath = $this->_checkAndGetFolderName( $sNewPath, $sPath );
            }
            else
            {
                $sNewPath = $this->_checkAndGetFileName( $sNewPath );
            }

            return rename( $sOldPath, $sNewPath ) ? basename( $sNewPath ) : $sOldName;
        }
        else
        {
            return false;
        }
    }

    public function moveFile( $sFileName, $sFolderName, $sThumb )
    {
        $sOldName = $this->getMediaPath() . $sFileName;
        $sNewName = $this->getMediaPath() . $sFolderName . '/' . $sFileName;

        if( ( $blReturn = rename( $sOldName, $sNewName ) ) )
        {
            if( $sThumb )
            {
                $sOldThumbPath = $this->getMediaPath() . 'thumbs/';
                $sNewThumbPath = $this->getMediaPath() . $sFolderName . '/thumbs/';

                if( !is_dir( $sNewThumbPath ) )
                {
                    mkdir( $sNewThumbPath );
                }

                foreach( glob( $sOldThumbPath . str_replace( 'thumb_' . $this->_iDefaultThumbnailSize . '.jpg', '*', $sThumb ) ) as $sThumbFile )
                {
                    rename( $sThumbFile, $sNewThumbPath . basename( $sThumbFile ) );
                }
            }
        }

        return $blReturn;
    }

    /**
     * @param string $sFile
     *
     * @return string
     */
    public function getThumbnailPath($sFile = '')
    {
        $sPath = $this->getMediaPath() . 'thumbs/';

        if ($sFile) {
            return $sPath . $sFile;
        }

        return $sPath;
    }

    /**
     * @param string       $sFileName
     * @param null|integer $iThumbSize
     * @param bool         $blCrop
     *
     * @return bool|string
     * @throws \Exception
     */
    public function createThumbnail($sFileName, $iThumbSize = null, $blCrop = true)
    {
        $sFilePath = $this->getMediaPath($sFileName);

        if (is_readable($sFilePath)) {
            if (!$iThumbSize) {
                $iThumbSize = $this->_iDefaultThumbnailSize;
            }

            list($iImageWidth, $iImageHeight, $iImageType) = getimagesize($sFilePath);

            switch ($iImageType) {
                case 1:
                    $rImg = imagecreatefromgif($sFilePath);
                    break;

                case 2:
                    $rImg = imagecreatefromjpeg($sFilePath);
                    break;

                case 3:
                    $rImg = imagecreatefrompng($sFilePath);
                    break;

                default:
                    throw new \Exception('Invalid filetype');
                    break;
            }

            $iThumbWidth = $iImageWidth;
            $iThumbHeight = $iImageHeight;

            $iThumbX = 0;
            $iThumbY = 0;

            if ($blCrop) {
                if ($iImageWidth < $iImageHeight) {
                    $iThumbWidth = $iThumbSize;
                    $iThumbHeight = $iImageHeight / ($iImageWidth / $iThumbWidth);

                    $iThumbY = (($iThumbSize - $iThumbHeight) / 2);
                } elseif ($iImageHeight < $iImageWidth) {
                    $iThumbHeight = $iThumbSize;
                    $iThumbWidth = $iImageWidth / ($iImageHeight / $iThumbHeight);

                    $iThumbX = (($iThumbSize - $iThumbWidth) / 2);
                }
            } else {
                if ($iImageWidth < $iImageHeight) {
                    if ($iImageHeight > $iThumbSize) {
                        $iThumbWidth *= ($iThumbSize / $iImageHeight);
                        $iThumbHeight *= ($iThumbSize / $iImageHeight);
                    }
                } elseif ($iImageHeight < $iImageWidth) {
                    if ($iImageHeight > $iThumbSize) {
                        $iThumbWidth *= ($iThumbSize / $iImageWidth);
                        $iThumbHeight *= ($iThumbSize / $iImageWidth);
                    }
                }
            }

            $rTmpImg = imagecreatetruecolor($iThumbWidth, $iThumbHeight);
            imagecopyresampled($rTmpImg, $rImg, $iThumbX, $iThumbY, 0, 0, $iThumbWidth, $iThumbHeight, $iImageWidth, $iImageHeight);

            if ($blCrop) {
                $rThumbImg = imagecreatetruecolor($iThumbSize, $iThumbSize);
                imagefill($rThumbImg, 0, 0, imagecolorallocate($rThumbImg, 0, 0, 0));

                imagecopymerge($rThumbImg, $rTmpImg, 0, 0, 0, 0, $iThumbSize, $iThumbSize, 100);
            } else {
                $rThumbImg = $rTmpImg;
            }

            $sThumbName = $this->getThumbName($sFileName, $iThumbSize);

            imagejpeg($rThumbImg, $this->getThumbnailPath($sThumbName));

            return $sThumbName;
        }

        return false;
    }

    /**
     * @param string $sFileName
     */
    public function createMoreThumbnails($sFileName)
    {
        // More Thumbnail Sizes
        $this->createThumbnail($sFileName, 300);
        $this->createThumbnail($sFileName, 800);
    }

    /**
     * @param null|integer $iThumbSize
     * @param bool         $blOverwrite
     * @param bool         $blCrop
     */
    public function generateThumbnails($iThumbSize = null, $blOverwrite = false, $blCrop = true)
    {
        if (!$iThumbSize) {
            $iThumbSize = $this->_iDefaultThumbnailSize;
        }

        if (is_dir($this->getMediaPath())) {
            foreach (new \DirectoryIterator($this->getMediaPath()) as $oFile) {
                if ($oFile->isFile()) {
                    $sThumbName = $this->getThumbName($oFile->getBasename(), $iThumbSize);
                    $sThumbPath = $this->getThumbnailPath($sThumbName);

                    if (!file_exists($sThumbPath) || $blOverwrite) {
                        $this->createThumbnail($oFile->getBasename(), $iThumbSize, $blCrop);
                    }
                }
            }
        }
    }

    public function validateFilename( $sFileName )
    {
        $aFileNameParts = explode( '.', $sFileName  );
        $aFileNameParts = array_reverse( $aFileNameParts );
        $sFileNameExt = $aFileNameParts[ 0 ];
        foreach( $this->_aFileExtBlacklist as $sBlacklistPattern )
        {
            if( preg_match( "/" . $sBlacklistPattern . "/", $sFileNameExt ) )
            {
                throw new \Exception( Registry::getLang()->translateString( 'DD_MEDIA_EXCEPTION_INVALID_FILEEXT' ) );
            }
        }
        return true;
    }

    public function setFolderNameForFolderId( $sId )
    {
        $iShopId = $this->getConfig()->getConfigParam( 'blMediaLibraryMultiShopCapability' ) ? $this->getConfig()->getActiveShop()->getShopId() : null;

        $oDb = DatabaseProvider::getDb( DatabaseProvider::FETCH_MODE_ASSOC );
        $sSelect = "SELECT `DDFILENAME` AS 'count' FROM `ddmedia` WHERE `OXID` = '$sId' AND `DDFILETYPE` = 'directory' " . ( $iShopId != null ? "AND `OXSHOPID` = " . $oDb->quote( $iShopId ) . " " : "" );
        $sFolderName = $oDb->getOne( $sSelect );

        if( $sFolderName )
        {
            $this->_sFolderName = $sFolderName;
        }
    }

    /**
     * @return mixed
     */
    public function getFolderName()
    {
        return $this->_sFolderName;
    }

    /**
     * @param $sFile
     */
    protected function _checkAndSetFolderName( $sFile )
    {
        if ( $sFile && ( $iPos = strpos( $sFile, '/' ) ) !== false && !$this->_sFolderName )
        {
            $sFolderName = substr( $sFile, 0, $iPos );
            if( $sFolderName != 'thumbs' )
            {
                $this->_sFolderName = substr( $sFile, 0, $iPos );
            }
        }
    }

    /**
     * @param $sNewPath
     * @param $sPath
     *
     * @return string
     */
    protected function _checkAndGetFolderName( $sNewPath, $sPath )
    {
        while ( file_exists( $sNewPath ) )
        {
            $sBaseName = basename( $sNewPath );

            $aBaseParts = explode( '_', $sBaseName );
            $aBaseParts = array_reverse( $aBaseParts );

            if ( strlen( $aBaseParts[ 0 ] ) && is_numeric( $aBaseParts[ 0 ] ) )
            {
                $iFileCount = (int) $aBaseParts[ 0 ];
                unset( $aBaseParts[ 0 ] );
            }

            $sBaseName = implode( '_', array_reverse( $aBaseParts ) );

            $sFileName = $sBaseName . '_' . ( ++$iFileCount );
            $sNewPath = $sPath . $sFileName;
        }

        return $sNewPath;
    }

    /**
     * @param $sDestPath
     *
     * @return array
     */
    protected function _checkAndGetFileName( $sDestPath )
    {
        $iFileCount = 0;

        while ( file_exists( $sDestPath ) )
        {
            $sFileName = basename( $sDestPath );

            $aFileParts = explode( '.', $sFileName );
            $aFileParts = array_reverse( $aFileParts );

            $sFileExt = $aFileParts[ 0 ];
            unset( $aFileParts[ 0 ] );

            $sBaseName = implode( '.', array_reverse( $aFileParts ) );

            $aBaseParts = explode( '_', $sBaseName );
            $aBaseParts = array_reverse( $aBaseParts );

            if ( strlen( $aBaseParts[ 0 ] ) == 1 && is_numeric( $aBaseParts[ 0 ] ) )
            {
                $iFileCount = (int) $aBaseParts[ 0 ];
                unset( $aBaseParts[ 0 ] );
            }

            $sBaseName = implode( '_', array_reverse( $aBaseParts ) );

            $sFileName = $sBaseName . '_' . ( ++$iFileCount ) . '.' . $sFileExt;
            $sDestPath = dirname( $sDestPath ) . '/' . $sFileName;
        }

        return $sDestPath;
    }

    /**
     * @param $sNewName
     *
     * @return mixed|null|string|string[]
     */
    protected function _sanitizeFilename( $sNewName )
    {
        $iLang = \OxidEsales\Eshop\Core\Registry::getLang()->getEditLanguage();
        if ( $aReplaceChars = \OxidEsales\Eshop\Core\Registry::getLang()->getSeoReplaceChars( $iLang ) )
        {
            $sNewName = str_replace( array_keys( $aReplaceChars ), array_values( $aReplaceChars ), $sNewName );
        }
        $sNewName = preg_replace( '/[^a-zA-Z0-9-_]+/', '-', pathinfo( $sNewName, PATHINFO_FILENAME ) ) . '.' . pathinfo( $sNewName, PATHINFO_EXTENSION );

        return $sNewName;
    }
}
