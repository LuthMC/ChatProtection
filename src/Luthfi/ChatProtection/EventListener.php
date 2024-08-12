<?php

namespace Luthfi\ChatProtection;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\player\Player;
use pocketmine\Server;

class EventListener implements Listener {

    private $plugin;
    private $messageCount = [];
    private $commandCount = [];
    private $chatLocked = false;

    public function __construct(ChatProtection $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();

        if ($this->chatLocked) {
            $event->cancel();
            $player->sendMessage($this->getMessage("chat_locked"));
            return;
        }

        if ($this->plugin->getConfig()->get("anti-spam")['enabled']) {
            $name = $player->getName();
            $this->messageCount[$name] = ($this->messageCount[$name] ?? 0) + 1;
            if ($this->messageCount[$name] > $this->plugin->getConfig()->get("anti-spam")['max_messages_per_second']) {
                $event->cancel();
                $player->sendMessage($this->getMessage("spam_warning"));
                $this->notifyAdmins("admin_notify_spam", $player->getName());
                if ($this->plugin->getConfig()->get("anti-spam")['kick_on_spam']) {
                    $player->kick($this->plugin->getConfig()->get("anti-spam")['kick_message']);
                }
            }
            $this->resetCounter($name, "message");
        }
    }

    public function onPlayerCommand(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();

        if ($this->plugin->getConfig()->get("anti-command-spam")['enabled']) {
            $name = $player->getName();
            $this->commandCount[$name] = ($this->commandCount[$name] ?? 0) + 1;
            if ($this->commandCount[$name] > $this->plugin->getConfig()->get("anti-command-spam")['max_commands_per_second']) {
                $event->cancel();
                $player->sendMessage($this->getMessage("command_spam_warning"));
                $this->notifyAdmins("admin_notify_command_spam", $player->getName());
                if ($this->plugin->getConfig()->get("anti-command-spam")['kick_on_spam']) {
                    $player->kick($this->plugin->getConfig()->get("anti-command-spam")['kick_message']);
                }
            }
            $this->resetCounter($name, "command");
        }
    }

    private function resetCounter(string $name, string $type): void {
        $plugin = $this->plugin;
        Server::getInstance()->getScheduler()->scheduleDelayedTask(new class($plugin, $name, $type) extends \pocketmine\scheduler\Task {
            private $plugin;
            private $name;
            private $type;

            public function __construct(ChatProtection $plugin, string $name, string $type) {
                $this->plugin = $plugin;
                $this->name = $name;
                $this->type = $type;
            }

            public function onRun(): void {
                if ($this->type === "message") {
                    $this->plugin->messageCount[$this->name] = 0;
                } else {
                    $this->plugin->commandCount[$this->name] = 0;
                }
            }
        }, 20);
    }

    private function notifyAdmins(string $messageKey, string $playerName): void {
        $message = $this->getMessage($messageKey, ["{player}" => $playerName]);
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($player->hasPermission("chatprotection.notify")) {
                $player->sendMessage($message);
            }
        }
    }

    private function getMessage(string $key, array $replacements = []): string {
        $message = $this->plugin->getConfig()->get("messages")[$key] ?? $key;
        $prefix = $this->plugin->getConfig()->get("messages")['prefix'] ?? "[ChatProtection] ";
        $message = str_replace("{prefix}", $prefix, $message);
        foreach ($replacements as $search => $replace) {
            $message = str_replace($search, $replace, $message);
        }
        return $message;
    }
}
