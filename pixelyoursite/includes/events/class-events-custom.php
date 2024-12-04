<?php
namespace PixelYourSite;
class EventsCustom extends EventsFactory {
    private static $_instance;
    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    static function getSlug() {
        return "custom";
    }

    private function __construct() {
        add_filter("pys_event_factory",[$this,"register"]);
    }

    function register($list) {
        $list[] = $this;
        return $list;
    }


    function getEvents(){
        return CustomEventFactory::get( 'active' );
    }

    function getCount()
    {
        if(!$this->isEnabled()) {
            return 0;
        }
        return count($this->getEvents());
    }

    function isEnabled()
    {
        return PYS()->getOption( 'custom_events_enabled' );
    }

    function getOptions()
    {
        return array();
    }

    /**
     * @param CustomEvent $event
     * @return bool
     */
    function isReadyForFire($event)
    {
        $event_triggers = $event->getTriggers();
        $isReady = array();
        if ( !empty( $event_triggers ) ) {
            foreach ($event_triggers as $event_trigger) {
                $trigger_type = $event_trigger->getTriggerType();
                switch ($trigger_type) {

                    case 'page_visit':
                    {
                        $triggers = $event_trigger->getPageVisitTriggers();
                        $isTriggerReady = !empty( $triggers ) && compareURLs( $triggers );
                        $event_trigger->setTriggerStatus( $isTriggerReady );
                        $isReady[] = $isTriggerReady;
                        break;
                    }
                    case 'home_page':
                    {
                        $isTriggerReady = is_front_page();
                        $event_trigger->setTriggerStatus( $isTriggerReady );
                        $isReady[] = $isTriggerReady;
                        break;
                    }

                }
            }
        }
        return in_array( true, $isReady );
    }
    /**
     * @param CustomEvent $event
     * @return PYSEvent
     */
    function getEvent($event)
    {
        $event_triggers = $event->getTriggers();
        $eventObject = null;
        if ( !empty( $event_triggers ) ) {
            foreach ( $event_triggers as $event_trigger ) {
                if ( $event_trigger->getTriggerStatus() ) {
                    $trigger_type = $event_trigger->getTriggerType();
                    switch ($trigger_type) {
                        case 'page_visit':
                        case 'home_page':
                        {
                            $singleEvent = new SingleEvent('custom_event',EventTypes::$STATIC,'custom');
                            $singleEvent->args = $event;
                            $singleEvent->addPayload(["custom_event_post_id" => $event->__get('post_id')]);
                            $delay = $event->getDelay();
                            if ( $delay > 0 ) {
                                $singleEvent->addPayload( [ "delay" => $delay ] );
                            }
                            return $singleEvent;
                        }
                    }
                }
            }
        }
    }
}
/**
 * @return EventsCustom
 */
function EventsCustom() {
    return EventsCustom::instance();
}

EventsCustom();
