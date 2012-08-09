<?php
/**
 * File containing the RelationList FieldType class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\FieldType\RelationList;

use eZ\Publish\Core\FieldType\FieldType;
use eZ\Publish\Core\FieldType\ValidationError;
use eZ\Publish\SPI\Persistence\Content\FieldValue;
use eZ\Publish\Core\Repository\Values\Content\ContentInfo;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentType;
use eZ\Publish\SPI\FieldType\Event;

/**
 * The RelationList field type.
 *
 * This field type represents a relation to a content.
 *
 * hash format ({@see fromhash()}, {@see toHash()}):
 * array( 'destinationContentIds' => array( (int)$destinationContentId ) );
 */
class Type extends FieldType
{
    const SELECTION_BROWSE = 0,
          SELECTION_DROPDOWN = 1;

    protected $settingsSchema = array(
        'selectionMethod' => array(
            'type' => 'int',
            'default' => self::SELECTION_BROWSE,
        ),
        'selectionRoot' => array(
            'type' => 'string',
            'default' => '',
        ),
    );

    /**
     * @see \eZ\Publish\Core\FieldType\FieldType::validateFieldSettings()
     *
     * @param mixed $fieldSettings
     *
     * @return \eZ\Publish\SPI\FieldType\ValidationError[]
     */
    public function validateFieldSettings( $fieldSettings )
    {
        $validationResult = array();

        foreach( array_keys( $fieldSettings ) as $setting )
        {
            if ( !in_array( $setting, array_keys( $this->settingsSchema ) ) )
            {
                $validationResult[] = new ValidationError(
                    "Unknown setting %setting%",
                    null,
                    array( 'setting' => $setting )
                );
            }
        }
        if ( $fieldSettings['selectionMethod'] != self::SELECTION_BROWSE && $fieldSettings['selectionMethod'] != self::SELECTION_DROPDOWN )
        {
            $validationResult[] = new ValidationError(
                "Setting selection method must be either %selection_browse% or %selection_dropdown%",
                null,
                array( 'selection_browse' => self::SELECTION_BROWSE, 'selection_dropdown' => self::SELECTION_DROPDOWN )
            );
        }

        if ( !is_string( $fieldSettings['selectionRoot'] ) && !is_numeric( $fieldSettings['selectionRoot'] ) )
        {
            $validationResult[] = new ValidationError(
                "Setting selection root must be either a string or numeric integer"
            );
        }

        return $validationResult;
    }

    /**
     * Return the field type identifier for this field type
     *
     * @return string
     */
    public function getFieldTypeIdentifier()
    {
        return "ezobjectrelationlist";
    }

    /**
     * Returns the name of the given field value.
     *
     * It will be used to generate content name and url alias if current field is designated
     * to be used in the content name/urlAlias pattern.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function getName( $value )
    {
        throw new \RuntimeException( '@todo Implement this method' );
    }

    /**
     * Returns the fallback default value of field type when no such default
     * value is provided in the field definition in content types.
     *
     * @return \eZ\Publish\Core\FieldType\RelationList\Value
     */
    public function getEmptyValue()
    {
        return new Value();
    }

    /**
     * Checks the type and structure of the $Value.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if the parameter is not of the supported value sub type
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if the value does not match the expected structure
     *
     * @param mixed $inputValue A ContentInfo, content ID or list of content ID's to build from, or a RelationList\Value
     *
     * @return \eZ\Publish\Core\FieldType\RelationList\Value
     */
    public function acceptValue( $inputValue )
    {
        // ContentInfo
        if ( $inputValue instanceof ContentInfo )
        {
            $inputValue = new Value( array( $inputValue->id ) );
        }
        // content id
        elseif ( is_integer( $inputValue ) || is_string( $inputValue ) )
        {
            $inputValue = new Value( array( $inputValue ) );
        }
        // content id's
        elseif ( is_array( $inputValue ) )
        {
            $inputValue = new Value( $inputValue );
        }

        if ( !$inputValue instanceof Value )
        {
            throw new InvalidArgumentType(
                '$inputValue',
                'eZ\\Publish\\Core\\Repository\\FieldType\\RelationList\\Value',
                $inputValue
            );
        }

        foreach ( $inputValue->destinationContentIds as $key => $destinationContentId )
        {
            if ( !is_integer( $destinationContentId ) && !is_string( $destinationContentId ) )
            {
               throw new InvalidArgumentType(
                    "\$inputValue->destinationContentIds[$key]",
                    'string|int',
                   $destinationContentId
                );
            }
        }

        return $inputValue;
    }

    /**
     * Returns information for FieldValue->$sortKey relevant to the field type.
     * For this FieldType, the related object's name is returned.
     *
     * @todo Repository needs to be rpovided to be able to get Content Relation name(s).
     * @return array
     */
    protected function getSortInfo( $value )
    {
        return (string)$value;
    }

    /**
     * Converts an $hash to the Value defined by the field type
     *
     * @param mixed $hash
     *
     * @return \eZ\Publish\Core\FieldType\RelationList\Value $value
     */
    public function fromHash( $hash )
    {
        return new Value( $hash['destinationContentIds'] );
    }

    /**
     * Converts a $Value to a hash
     *
     * @param \eZ\Publish\Core\FieldType\RelationList\Value $value
     *
     * @return mixed
     */
    public function toHash( $value )
    {
        return array( 'destinationContentIds' => $value->destinationContentIds );
    }

    /**
     * Returns whether the field type is searchable
     *
     * @return bool
     */
    public function isSearchable()
    {
        return true;
    }

    /**
     * Converts a $value to a persistence value
     *
     * @param mixed $value
     *
     * @return \eZ\Publish\SPI\Persistence\Content\FieldValue
     */
    public function toPersistenceValue( $value )
    {
        return new FieldValue(
            array(
                "data" => $this->toHash( $value ),
                "externalData" => $this->toHash( $value ),
                "sortKey" => null,
            )
        );
    }

    /**
     * @see \eZ\Publish\Core\FieldType
     *
     * @param \eZ\Publish\SPI\Persistence\Content\FieldValue $fieldValue
     *
     * @return mixed
     */
    public function fromPersistenceValue( FieldValue $fieldValue )
    {
        return $this->fromHash( $fieldValue->data );
    }

    /**
     * Events handler (prePublish, postPublish, preCreate, postCreate)
     *
     * @param string $event - prePublish, postPublish, preCreate, postCreate
     * @param InternalRepository $repository
     * @param $fieldDef - the field definition of the field
     * @param $field - the field for which an action is performed
     */
    public function handleEvent( Event $event )
    {

    }
}
