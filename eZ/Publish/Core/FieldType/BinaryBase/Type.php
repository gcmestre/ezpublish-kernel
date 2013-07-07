<?php
/**
 * File containing the BinaryBase Type class
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\FieldType\BinaryBase;

use eZ\Publish\Core\FieldType\FieldType;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentType;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentValue;
use eZ\Publish\Core\FieldType\ValidationError;
use eZ\Publish\API\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\SPI\FieldType\Value as SPIValue;
use eZ\Publish\SPI\Persistence\Content\FieldValue as PersistenceValue;
use eZ\Publish\Core\FieldType\Value as BaseValue;

/**
 * Base FileType class for Binary field types (i.e. BinaryBase & Media)
 */
abstract class Type extends FieldType
{
    /**
     * @see eZ\Publish\Core\FieldType::$validatorConfigurationSchema
     */
    protected $validatorConfigurationSchema = array(
        "FileSizeValidator" => array(
            'maxFileSize' => array(
                'type' => 'int',
                'default' => false,
            )
        )
    );

    /**
     * Creates a specific value of the derived class from $inputValue
     *
     * @param array $inputValue
     *
     * @return Value
     */
    abstract protected function createValue( array $inputValue );

    /**
     * Returns the name of the given field value.
     *
     * It will be used to generate content name and url alias if current field is designated
     * to be used in the content name/urlAlias pattern.
     *
     * @param \eZ\Publish\Core\FieldType\BinaryBase\Value $value
     *
     * @return string
     */
    public function getName( SPIValue $value )
    {
        return $value->fileName;
    }

    /**
     * Inspects given $inputValue and potentially converts it into a dedicated value object.
     *
     * @param string|array|\eZ\Publish\Core\FieldType\BinaryBase\Value $inputValue
     *
     * @return \eZ\Publish\Core\FieldType\BinaryBase\Value The potentially converted and structurally plausible value.
     */
    protected function createValueFromInput( $inputValue )
    {
        // construction only from path
        if ( is_string( $inputValue ) )
        {
            $inputValue = array( 'path' => $inputValue );
        }

        // default construction from array
        if ( is_array( $inputValue ) )
        {
            $inputValue = $this->createValue( $inputValue );
        }

        $this->completeValue( $inputValue );

        return $inputValue;
    }

    /**
     * Throws an exception if value structure is not of expected format.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException If the value does not match the expected structure.
     *
     * @param \eZ\Publish\Core\FieldType\BinaryBase\Value $value
     *
     * @return void
     */
    protected function checkValueStructure( BaseValue $value )
    {
        // Required parameter $path
        if ( !isset( $value->path ) || !file_exists( $value->path ) )
        {
            throw new InvalidArgumentValue(
                '$value->path',
                $value->path,
                get_class( $this )
            );
        }

        // Required parameter $fileName
        if ( !isset( $value->fileName ) || !is_string( $value->fileName ) )
        {
            throw new InvalidArgumentType(
                '$value->fileName',
                'string',
                $value->fileName
            );
        }

        // Optional parameter $fileSize
        if ( isset( $value->fileSize ) && !is_int( $value->fileSize ) )
        {
            throw new InvalidArgumentType(
                '$value->fileSize',
                'int',
                $value->fileSize
            );
        }
    }

    /**
     * Attempts to complete the data in $value
     *
     * @param mixed $value
     *
     * @return void
     */
    protected function completeValue( $value )
    {
        if ( !isset( $value->path ) || !file_exists( $value->path ) )
        {
            return;
        }

        if ( !isset( $value->fileName ) )
        {
            $value->fileName = basename( $value->path );
        }

        if ( !isset( $value->fileSize ) )
        {
            $value->fileSize = filesize( $value->path );
        }
    }

    /**
     * BinaryBase does not support sorting
     *
     * @param \eZ\Publish\Core\FieldType\BinaryBase\Value $value
     *
     * @return boolean
     */
    protected function getSortInfo( BaseValue $value )
    {
        return false;
    }

    /**
     * Converts an $hash to the Value defined by the field type
     *
     * @param mixed $hash
     *
     * @return \eZ\Publish\Core\FieldType\BinaryBase\Value $value
     */
    public function fromHash( $hash )
    {
        if ( $hash === null )
        {
            return $this->getEmptyValue();
        }

        return $this->createValue( $hash );
    }

    /**
     * Converts a $Value to a hash
     *
     * @param \eZ\Publish\Core\FieldType\BinaryBase\Value $value
     *
     * @return mixed
     */
    public function toHash( SPIValue $value )
    {
        return array(
            'fileName' => $value->fileName,
            'fileSize' => $value->fileSize,
            'path' => $value->path,
            'mimeType' => $value->mimeType,
        );
    }

    /**
     * Converts a $value to a persistence value.
     *
     * In this method the field type puts the data which is stored in the field of content in the repository
     * into the property FieldValue::data. The format of $data is a primitive, an array (map) or an object, which
     * is then canonically converted to e.g. json/xml structures by future storage engines without
     * further conversions. For mapping the $data to the legacy database an appropriate Converter
     * (implementing eZ\Publish\Core\Persistence\Legacy\FieldValue\Converter) has implemented for the field
     * type. Note: $data should only hold data which is actually stored in the field. It must not
     * hold data which is stored externally.
     *
     * The $externalData property in the FieldValue is used for storing data externally by the
     * FieldStorage interface method storeFieldData.
     *
     * The FieldValuer::sortKey is build by the field type for using by sort operations.
     *
     * @see \eZ\Publish\SPI\Persistence\Content\FieldValue
     *
     * @param \eZ\Publish\Core\FieldType\BinaryBase\Value $value The value of the field type
     *
     * @return \eZ\Publish\SPI\Persistence\Content\FieldValue the value processed by the storage engine
     */
    public function toPersistenceValue( SPIValue $value )
    {
        // Store original data as external (to indicate they need to be stored)
        return new PersistenceValue(
            array(
                "data" => null,
                "externalData" => $this->toHash( $value ),
                "sortKey" => $this->getSortInfo( $value ),
            )
        );
    }

    /**
     * Converts a persistence $fieldValue to a Value
     *
     * This method builds a field type value from the $data and $externalData properties.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\FieldValue $fieldValue
     *
     * @return \eZ\Publish\Core\FieldType\BinaryBase\Value
     */
    public function fromPersistenceValue( PersistenceValue $fieldValue )
    {
        // Restored data comes in $data, since it has already been processed
        // there might be more data in the persistence value than needed here
        $result = $this->fromHash(
            array(
                'path' => ( isset( $fieldValue->externalData['path'] )
                    ? $fieldValue->externalData['path']
                    : null ),
                'fileName' => ( isset( $fieldValue->externalData['fileName'] )
                    ? $fieldValue->externalData['fileName']
                    : null ),
                'fileSize' => ( isset( $fieldValue->externalData['fileSize'] )
                    ? $fieldValue->externalData['fileSize']
                    : null ),
                'mimeType' => ( isset( $fieldValue->externalData['mimeType'] )
                    ? $fieldValue->externalData['mimeType']
                    : null ),
            )
        );
        return $result;
    }

    /**
     * Validates a field based on the validators in the field definition
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     *
     * @param \eZ\Publish\API\Repository\Values\ContentType\FieldDefinition $fieldDefinition The field definition of the field
     * @param \eZ\Publish\Core\FieldType\BinaryBase\Value $fieldValue The field value for which an action is performed
     *
     * @return \eZ\Publish\SPI\FieldType\ValidationError[]
     */
    public function validate( FieldDefinition $fieldDefinition, SPIValue $fieldValue )
    {
        $errors = array();

        if ( $this->isEmptyValue( $fieldValue ) )
        {
            return $errors;
        }

        foreach ( (array)$fieldDefinition->getValidatorConfiguration() as $validatorIdentifier => $parameters )
        {
            switch ( $validatorIdentifier )
            {
                case 'FileSizeValidator':
                    if ( !isset( $parameters['maxFileSize'] ) || $parameters['maxFileSize'] == false )
                    {
                        // No file size limit
                        break;
                    }
                    // Database stores maxFileSize in MB
                    if ( ( $parameters['maxFileSize'] * 1024 * 1024 ) < $fieldValue->fileSize )
                    {
                        $errors[] = new ValidationError(
                            "The file size cannot exceed %size% byte.",
                            "The file size cannot exceed %size% bytes.",
                            array(
                                "size" => $parameters['maxFileSize'],
                            )
                        );
                    }
                    break;
            }
        }
        return $errors;
    }

    /**
     * Validates the validatorConfiguration of a FieldDefinitionCreateStruct or FieldDefinitionUpdateStruct
     *
     * @param mixed $validatorConfiguration
     *
     * @return \eZ\Publish\SPI\FieldType\ValidationError[]
     */
    public function validateValidatorConfiguration( $validatorConfiguration )
    {
        $validationErrors = array();

        foreach ( $validatorConfiguration as $validatorIdentifier => $parameters )
        {
            switch ( $validatorIdentifier )
            {
                case 'FileSizeValidator':
                    if ( !isset( $parameters['maxFileSize'] ) )
                    {
                        $validationErrors[] = new ValidationError(
                            "Validator %validator% expects parameter %parameter% to be set.",
                            null,
                            array(
                                "validator" => $validatorIdentifier,
                                "parameter" => 'maxFileSize',
                            )
                        );
                        break;
                    }
                    if ( !is_int( $parameters['maxFileSize'] ) && $parameters['maxFileSize'] !== false )
                    {
                        $validationErrors[] = new ValidationError(
                            "Validator %validator% expects parameter %parameter% to be of %type%.",
                            null,
                            array(
                                "validator" => $validatorIdentifier,
                                "parameter" => 'maxFileSize',
                                "type" => 'integer',
                            )
                        );
                    }
                    break;
                default:
                    $validationErrors[] = new ValidationError(
                        "Validator '%validator%' is unknown",
                        null,
                        array(
                            "validator" => $validatorIdentifier
                        )
                    );
            }
        }

        return $validationErrors;
    }

    /**
     * Returns whether the field type is searchable
     *
     * @return boolean
     */
    public function isSearchable()
    {
        return true;
    }
}
