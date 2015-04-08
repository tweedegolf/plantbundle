<?php


namespace TweedeGolf\PlantBundle\Retriever;


abstract class AbstractRetriever
{
    /**
     * @var string ("en", "nl", etc)
     */
    protected $locale;

    /**
     * Sets the locale property based on the given $arg. The argument can be either the Symfony request stack
     * from which the locale is taken in that case or a string to set the locale manually, for example
     * in the ElasticaCommand
     *
     * @param $arg string | RequestStack
     */
    public function setLocale($arg)
    {
        if (is_string($arg)) {
            $this->locale = $arg;

            return $this;
        }

        if (is_object($arg) && get_class($arg) === 'Symfony\Component\HttpFoundation\RequestStack') {
            if (($request = $arg->getCurrentRequest()) !== null) {
                $this->locale = $request->getLocale();
            }

            return $this;
        }

        throw \InvalidArugmentException('The argument provided should be either a string or the RequestStack');
    }
}