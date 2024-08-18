<?php

namespace Luthfi\ChatProtection;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

class EventListener implements Listener {

    private $plugin;
    private $messageCount = [];
    private $commandCount = [];
    private $lastMessages = [];
    private $chatLocked = false;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $message = $event->getMessage();

        if ($this->chatLocked) {
            $event->cancel();
            $player->sendMessage($this->plugin->getMessage("chat_locked"));
            return;
        }

        if ($this->plugin->getConfig()->get("anti-swear")['enabled']) {
            $swearWords = $this->plugin->getConfig()->get("anti-swear")['swear_words'];
            $normalizedMessage = $this->normalizeMessage($message);

            foreach ($swearWords as $swearWord) {
                $normalizedSwearWord = $this->normalizeMessage($swearWord);

                if (stripos($normalizedMessage, $normalizedSwearWord) !== false) {
                    $event->cancel();
                    $player->sendMessage($this->plugin->getMessage("anti-swear")['warning_message']);
                    $this->notifyAdmins("admin_notify_swear", $player->getName());
                    if ($this->plugin->getConfig()->get("anti-swear")['kick_on_swear']) {
                        $player->kick($this->plugin->getMessage("anti-swear")['kick_message']);
                    }
                    return;
                }
            }
        }

        if ($this->plugin->getConfig()->get("anti-message-repeat")['enabled']) {
            $cooldown = $this->plugin->getConfig()->get("anti-message-repeat")['cooldown'];
            $lastMessageTime = $this->lastMessages[$name] ?? 0;

            if (time() - $lastMessageTime < $cooldown && $this->lastMessages[$name]['message'] === $message) {
                $event->cancel();
                $player->sendMessage($this->plugin->getMessage("anti-message-repeat")['warning_message']);
                $this->notifyAdmins("admin_notify_message_repeat", $player->getName());
                if ($this->plugin->getConfig()->get("anti-message-repeat")['kick_on_repeat']) {
                    $player->kick($this->plugin->getConfig()->get("anti-message-repeat")['kick_message']);
                }
                return;
            }
            $this->lastMessages[$name] = ['message' => $message, 'time' => time()];
        }
       
        if ($this->plugin->getConfig()->get("anti-spam")['enabled']) {
            $name = $player->getName();
            $this->messageCount[$name] = ($this->messageCount[$name] ?? 0) + 1;
            if ($this->messageCount[$name] > $this->plugin->getConfig()->get("anti-spam")['max_messages_per_second']) {
                $event->cancel();
                $player->sendMessage($this->plugin->getMessage("spam_warning"));
                $this->notifyAdmins("admin_notify_spam", $player->getName());
                if ($this->plugin->getConfig()->get("anti-spam")['kick_on_spam']) {
                    $player->kick($this->plugin->getConfig()->get("anti-spam")['kick_message']);
                }
            }
            $this->resetCounter($name, "message");
        }

        if ($this->plugin->getConfig()->get("anti-caps")['enabled']) {
            $capsMessage = preg_replace('/[^A-Z]/', '', $message);
            $capsRatio = strlen($capsMessage) / strlen($message) * 100;
            if (strlen($message) >= $this->plugin->getConfig()->get("anti-caps")['min_length'] &&
                $capsRatio > $this->plugin->getConfig()->get("anti-caps")['caps_threshold']) {
                $event->cancel();
                $player->sendMessage($this->plugin->getMessage("anti-caps")['warning_message']);
            }
        }

        if ($this->plugin->getConfig()->get("anti-advertise")['enabled']) {
            foreach ($this->plugin->getConfig()->get("anti-advertise")['blocked_domains'] as $domain) {
                if (stripos($message, $domain) !== false) {
                    $event->cancel();

                    $warningMessage = $this->plugin->getConfig()->get("anti-advertise")['warning_message'];
                    $kickMessage = $this->plugin->getConfig()->get("anti-advertise")['kick_message'];

                    $player->sendMessage($this->plugin->getMessage($warningMessage));
                    $this->notifyAdmins("admin_notify_advertise", $player->getName());

                    if ($this->plugin->getConfig()->get("anti-advertise")['kick_on_advertise']) {
                        $player->kick($this->plugin->getMessage($kickMessage));
                    }
                    return;
                }
            }
        }
    }

    public function onPlayerCommand(CommandEvent $event): void {
        $sender = $event->getSender();

        if ($sender instanceof Player) {
            $player = $sender;

            if ($this->plugin->getConfig()->get("anti-command-spam")['enabled']) {
                $name = $player->getName();
                $this->commandCount[$name] = ($this->commandCount[$name] ?? 0) + 1;
                if ($this->commandCount[$name] > $this->plugin->getConfig()->get("anti-command-spam")['max_commands_per_second']) {
                    $event->cancel();
                    $player->sendMessage($this->plugin->getMessage("command_spam_warning"));
                    $this->notifyAdmins("admin_notify_command_spam", $player->getName());
                    if ($this->plugin->getConfig()->get("anti-command-spam")['kick_on_spam']) {
                        $player->kick($this->plugin->getConfig()->get("anti-command-spam")['kick_message']);
                    }
                }
                $this->resetCounter($name, "command");
            }
        }
    }

    private function resetCounter(string $name, string $type): void {
        $scheduler = $this->plugin->getScheduler();
        $scheduler->scheduleDelayedTask(new ClosureTask(function() use ($name, $type): void {
            if ($type === "message") {
                $this->plugin->messageCount[$name] = 0;
            } else {
                $this->plugin->commandCount[$name] = 0;
            }
        }), 20);
    }

    private function notifyAdmins(string $messageKey, string $playerName): void {
        $message = $this->plugin->getMessage($messageKey, ["{player}" => $playerName]);
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($player->hasPermission("chatprotection.notify")) {
                $player->sendMessage($message);
            }
        }
    }

    public function lockChat(): void {
        $this->chatLocked = true;
    }

    public function unlockChat(): void {
        $this->chatLocked = false;
    }
    
    public function getMessage(string $key, array $replacements = []): string {
        $message = $this->plugin->getMessage($key, $replacements);
        return $message;
    }
}
