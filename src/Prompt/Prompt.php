<?php
namespace Guywithnose\ReleaseNotes\Prompt;

use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class Prompt
{
    /** @type \Symfony\Component\Console\Input\InputInterface The command input. */
    protected $_input;

    /** @type \Symfony\Component\Console\Output\OutputInterface The command output. */
    protected $_output;

    /** @type \Symfony\Component\Console\Helper\QuestionHelper The question helper. */
    protected $_questionHelper;

    /** @type \Symfony\Component\Console\Helper\FormatterHelper The formatter helper. */
    protected $_formatter;

    /** @type string The question to ask. */
    protected $_question;

    /** @type mixed The default answer to the prompt. */
    protected $_default;

    /** @type array The chocies/suggestions for the prompt. */
    protected $_choices;

    /** @type string A preamble to display before asking the question. */
    protected $_preamble;

    /** @type boolean True if the choices are the only available answers, false otherwise. */
    protected $_choicesOnly;

    /**
     * Create the change from a github API commit representation.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @param \Symfony\Component\Console\Helper\QuestionHelper $questionHelper The question helper.
     * @param \Symfony\Component\Console\Helper\FormatterHelper $formatter The formatter helper.
     * @param string $question The question to ask.
     * @param mixed $default The default answer to the prompt.
     * @param array $choices The chocies/suggestions for the prompt.
     * @param string $preamble A preamble to display before asking the question.
     * @param boolean $choicesOnly True if the choices are the only available answers, false otherwise.
     * @return \Guywithnose\ReleaseNotes\Prompt The prompt object.
     */
    public function __construct(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper,
        FormatterHelper $formatter,
        $question,
        $default = null,
        array $choices = [],
        $preamble = '',
        $choicesOnly = true
    )
    {
        $this->_input = $input;
        $this->_output = $output;
        $this->_questionHelper = $questionHelper;
        $this->_formatter = $formatter;
        $this->_question = $question;
        $this->_default = $default;
        $this->_choices = $choices;
        $this->_preamble = $preamble;
        $this->_choicesOnly = $choicesOnly;
    }

    /**
     * Executes the question returning the result.
     *
     * The question may be a select, confirmation (yes/no), or textual prompt.
     *
     * @return string|bool The user's response.
     */
    public function __invoke()
    {
        return $this->_questionHelper->ask($this->_input, $this->_output, $this->_getQuestion());
    }

    /**
     * Gets the question object that is to be asked.
     *
     * The question will either be a ChoiceQuestion, ConfirmationQuestion, or regular Question based on the configuration.
     *
     * @return \Symfony\Component\Console\Question\Question The question to be asked.
     */
    protected function _getQuestion()
    {
        if ($this->_isSelect()) {
            return new ChoiceQuestion($this->_displayQuestion(), $this->_choices, $this->_default);
        }

        if ($this->_isConfirmation()) {
            return new ConfirmationQuestion($this->_displayQuestion(), $this->_default);
        }

        $question = new Question($this->_displayQuestion(), $this->_default);
        $question->setAutocompleterValues($this->_choices);

        return $question;
    }

    /**
     * Check if this question is a select prompt.
     *
     * When there are choices given (rather than freeform input or boolean), they can either be given via autocomplete suggestions (in case the
     * choices are only loose choices) or via a select element (for when the choices are the only allowed responses).
     *
     * @return bool True if this should be a select prompt, false if not.
     */
    protected function _isSelect()
    {
        return !empty($this->_choices) && $this->_choicesOnly;
    }

    /**
     * Check if this question is a confirmation prompt.
     *
     * If the default value is a boolean value, then we assume that the question is yes/no.
     *
     * @return bool True if this should be a confirmation prompt, false if not.
     */
    protected function _isConfirmation()
    {
        return is_bool($this->_default);
    }

    /**
     * Formats the question for display, including (as given) a preamble, the question text, and the default value.
     *
     * @return string The formatted question.
     */
    protected function _displayQuestion()
    {
        $question = trim("{$this->_displayPreamble()}\n<question>{$this->_question}</question>");

        if ($this->_default !== null) {
            $question .= " <info>(default: {$this->_displayDefault()})</info>";
        }

        $question .= ': ';

        return $question;
    }

    /**
     * Formats the preamble for display.
     *
     * @return string The formatted preamble.
     */
    protected function _displayPreamble()
    {
        return $this->_preamble ? $this->_formatter->formatBlock(explode("\n", $this->_preamble), 'info', true) : '';
    }

    /**
     * Formats the default value for display.
     *
     * Select questions are special in that they need both key and value and the default should mention them both to be helpful.
     *
     * Confirmation questions are special in that the boolean value needs to be converted to a "yes" or "no" string.
     *
     * @return string The formatted default value.
     */
    protected function _displayDefault()
    {
        if ($this->_isSelect()) {
            return "{$this->_default} \"{$this->_choices[$this->_default]}\"";
        }

        if ($this->_isConfirmation()) {
            return $this->_default ? 'yes' : 'no';
        }

        return $this->_default;
    }
}
