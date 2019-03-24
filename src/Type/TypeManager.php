<?php
namespace Guywithnose\ReleaseNotes\Type;

final class TypeManager
{
    /**
     * @type array list of useable types
     */
    private $_types;

    /**
     * @type Type the lowest type is Backward compatible breaking
     */
    private $_bcType;

    /**
     * @type Type the lowest type is a major change
     */
    private $_majorType;

    /**
     * @type Type the lowest type is a minor change
     */
    private $_minorType;

    /**
     * @type Type the default type to use
     */
    private $_defaultType;

    public function __construct()
    {
        $this->_types = [];
        $this->_bcType = null;
        $this->_majorType = null;
        $this->_minorType = null;
        $this->_defaultType = null;
    }

    public function setBCType(Type $type)
    {
        $this->_bcType = $type;
    }

    public function getBCType()
    {
        return $this->_bcType;
    }

    public function setMajorType(Type $type)
    {
        $this->_majorType = $type;
    }

    public function getMajorType()
    {
        return $this->_majorType;
    }

    public function setMinorType(Type $type)
    {
        $this->_minorType = $type;
    }

    public function getMinorType()
    {
        return $this->_minorType;
    }

    public function setDefaultType(Type $type)
    {
        $this->_defaultType = $type;
    }

    public function getDefaultType()
    {
        return $this->_defaultType;
    }

    public function add(Type $type)
    {
        if ($this->getTypeByCode($type->getCode()) !== null) {
            throw new TypeCodeExistsException('Type with code ' . $type->getCode() . 'already exists.');
        }

        $this->_types[] = $type;

        usort($this->_types, [Type::class, 'rcmp']);
    }

    /**
     * @return Type|null the type if found or null otherwise
     */
    public function getTypeByCode(string $code)
    {
        foreach ($this->_types as $type) {
            if ($type->getCode() === $code) {
                return $type;
            }
        }
    }

    /**
     * Returns an array of key values pairs for use in menu selection
     *
     * @return array
     */
    public function getTypesForCommand() : array
    {
        $data = [];
        foreach ($this->_types as $type) {
            $data[$type->getCode()] = $type->getDescription();
        }

        return $data;
    }

    public static function getSemanticTypeManager()
    {
        $manager = new TypeManager();

        $manager->add(new Type('Backward Compatible Breakers', 'B', 'Backward Compatibility Breakers', 100));
        $manager->add(new Type('Major', 'M', 'Major Features', 80));
        $manager->add(new Type('Minor', 'm', 'Minor Features', 60));
        $manager->add(new Type('Bug', 'b', 'Bug Fixes', 40));
        $manager->add(new Type('Developer', 'd', 'Developer Changes', 20));
        $manager->add(new Type('Ignore', 'x', 'Remove Pull Request from Release Notes', 0));

        $manager->setBCType($manager->getTypeByCode('B'));
        $manager->setMajorType($manager->getTypeByCode('M'));
        $manager->setMinorType($manager->getTypeByCode('m'));
        $manager->setDefaultType($manager->getTypeByCode('m'));

        return $manager;
    }
}
