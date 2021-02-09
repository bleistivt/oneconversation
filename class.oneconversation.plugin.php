<?php

class OneConversationPlugin extends Gdn_Plugin {

    public function messagesController_render_before($sender) {
        if (!Gdn::config('oneconversation.messageButton', true)) {
            return;
        }
        // Check if the message editor has been opened with a single recipient.
        if (inSection('PostConversation') && count($sender->RequestArgs) == 1) {
            $to = Gdn::userModel()->getByUsername($sender->RequestArgs[0]);
            // Is there already a conversation with this recipient?
            if ($to && ($id = self::conversation(Gdn::session()->UserID, $to->UserID))) {
                redirectTo('messages/'.$id.'#MessageForm');
            }
        }
    }


    public function messagesController_beforeAddConversation_handler($sender, $args) {
        if (!Gdn::config('oneconversation.autoAppend', true) || count($args['Recipients']) != 1) {
            return;
        }
        if ($id = self::conversation(Gdn::session()->UserID, $args['Recipients'][0])) {
            // Respond to an existing conversation instead of starting a new one.
            $sender->ConversationMessageModel->save([
                'ConversationID' => $id,
                'Body' => $sender->Form->getFormValue('Body'),
                'Format' => $sender->Form->getFormValue('Format')
            ]);
            redirectTo('messages/'.$id.'#MessageForm');
        }
    }


    // Find the most recent conversation between two users.
    private static function conversation($from, $to) {
        // Find all conversations that the sender has participated in.
        $fromIDs = Gdn::sql()
            ->select('uc.ConversationID')
            ->from('UserConversation uc')
            ->join('Conversation c', 'uc.ConversationID = c.ConversationID')
            ->where('c.CountParticipants', 2)
            ->where('uc.UserID', $from)
            ->get()->resultArray();

        // Find all conversations that the recipient has participated in.
        $toIDs = Gdn::sql()
            ->select('uc.ConversationID')
            ->from('UserConversation uc')
            ->join('Conversation c', 'uc.ConversationID = c.ConversationID')
            ->where('c.CountParticipants', 2)
            ->where('uc.UserID', $to)
            ->get()->resultArray();

        // Find all ConversationIDs where both users have participated.
        $result = array_intersect(
            array_column($fromIDs, 'ConversationID'),
            array_column($toIDs, 'ConversationID')
        );
        return empty($result) ? false : max($result);
    }


    public function settingsController_oneConversation_create($sender) {
        $sender->permission('Garden.Settings.Manage');

        $conf = new ConfigurationModule($sender);
        $conf->initialize([
            'oneconversation.messageButton' => [
                'Control' => 'checkbox',
                'LabelCode' => 'Redirect "Message" button on profile pages to the most recent conversation.',
                'Default' => true
            ],
            'oneconversation.autoAppend' => [
                'Control' => 'checkbox',
                'LabelCode' => 'Automatically add new conversation messages to the most recent conversation.',
                'Default' => true
            ]
        ]);

        $sender->title(sprintf(Gdn::translate('%s Settings'), 'One Conversation'));
        $conf->renderAll();
    }

}
