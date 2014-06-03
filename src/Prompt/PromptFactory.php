<?php
namespace Guywithnose\ReleaseNotes\Prompt;

use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\OutputInterface;

class PromptFactory
{
    /** @type \Symfony\Component\Console\Output\OutputInterface The command output. */
    protected $_output;

    /** @type \Symfony\Component\Console\Helper\DialogHelper The dialog helper. */
    protected $_dialog;

    /** @type \Symfony\Component\Console\Helper\FormatterHelper The formatter helper. */
    protected $_formatter;

    /**
     * Initialize the prompt factory.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @param \Symfony\Component\Console\Helper\DialogHelper $dialog The dialog helper.
     * @param \Symfony\Component\Console\Helper\FormatterHelper $formatter The formatter helper.
     */
    public function __construct(OutputInterface $output, DialogHelper $dialog, FormatterHelper $formatter)
    {
        $this->_output = $output;
        $this->_dialog = $dialog;
        $this->_formatter = $formatter;
    }

    /**
     * Create the prompt.
     *
     * @param string $question The question to ask.
     * @param mixed $default The default answer to the prompt.
     * @param array $choices The chocies/suggestions for the prompt.
     * @param string $preamble A preamble to display before asking the question.
     * @param boolean $choicesOnly True if the choices are the only available answers, false otherwise.
     * @return \Guywithnose\ReleaseNotes\Prompt The prompt object.
     */
    public function create($question, $default = null, array $choices = [], $preamble = '', $choicesOnly = true)
    {
        return new Prompt($this->_output, $this->_dialog, $this->_formatter, $question, $default, $choices, $preamble, $choicesOnly);
    }

    /**
     * Create and immediately invoke the prompt returning the result.
     *
     * @param string $question The question to ask.
     * @param mixed $default The default answer to the prompt.
     * @param array $choices The chocies/suggestions for the prompt.
     * @param string $preamble A preamble to display before asking the question.
     * @param boolean $choicesOnly True if the choices are the only available answers, false otherwise.
     * @return mixed The response from the prompt.
     */
    public function invoke($question, $default = null, array $choices = [], $preamble = '', $choicesOnly = true)
    {
        $prompt = $this->create($question, $default, $choices, $preamble, $choicesOnly);

        return $prompt();
    }
}
