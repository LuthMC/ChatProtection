<?php

namespace Luthfi\ChatProtection;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    private $eventListener;
    private $messages;
    private $staffChat = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        $this->eventListener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($this->eventListener, $this);
        $this->loadMessages();
        $this->getLogger()->info("ChatProtection Enabled");
    }

    private function loadMessages(): void {
        $this->messages = $this->messages->getAll()['messages'] ?? [];
    }

    private function getMessage(string $key, array $replacements = []): string {
        $message = $this->messages[$key] ?? $key;
        $prefix = $this->messages['prefix'] ?? "[ChatProtection] ";
        $message = str_replace("{prefix}", $prefix, $message);
        foreach ($replacements as $search => $replace) {
            $message = str_replace($search, $replace, $message);
        }
        return $message;
    }
    
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch ($command->getName()) {
            case "lock":
                if ($sender->hasPermission("chatprotection.lock")) {
                    $this->eventListener->lockChat();
                    $this->getServer()->broadcastMessage($this->getMessage("chat_locked_by_admin"));
                    return true;
                }
                $sender->sendMessage($this->getMessage("no_permission"));
                return false;

            case "unlock":
                if ($sender->hasPermission("chatprotection.unlock")) {
                    $this->eventListener->unlockChat();
                    $this->getServer()->broadcastMessage($this->getMessage("chat_unlocked_by_admin"));
                    return true;
                }
                $sender->sendMessage($this->getMessage("no_permission"));
                return false;

            case "clearchat":
                if ($sender->hasPermission("chatprotection.clearchat")) {
                    $this->clearChat();
                    $sender->sendMessage($this->getMessage("chat_cleared"));
                    return true;
                }
                $sender->sendMessage($this->getMessage("no_permission"));
                return false;

            case "staffchat":
                if ($sender->hasPermission("chatprotection.staff") || $sender->isOp()) {
                    if (isset($args[0]) && strtolower($args[0]) === "toggle") {
                        $this->toggleStaffChat($sender);
                        $sender->sendMessage($this->getMessage("staffchat_toggled", ["{status}" => $this->isStaffChatEnabled($sender) ? "enabled" : "disabled"]));
                        return true;
                    }
                    $message = implode(" ", $args);
                    $this->sendStaffChatMessage($sender, $message);
                    return true;
                }
                $sender->sendMessage($this->getMessage("no_permission"));
                return false;
        }
        return false;
    }

    private function clearChat(): void {
        $clearMessage = str_repeat("\n", 100);
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $player->sendMessage($clearMessage);
        }
    }

    private function toggleStaffChat(CommandSender $sender): void {
        if (!isset($this->staffChat[$sender->getName()])) {
            $this->staffChat[$sender->getName()] = false;
        }
        $this->staffChat[$sender->getName()] = !$this->staffChat[$sender->getName()];
    }

    private function isStaffChatEnabled(CommandSender $sender): bool {
        return isset($this->staffChat[$sender->getName()]) && $this->staffChat[$sender->getName()];
    }

    private function sendStaffChatMessage(CommandSender $sender, string $message): void {
        $formattedMessage = $this->getMessage("staffchat_message", ["{player}" => $sender->getName(), "{message}" => $message]);
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            if ($player->hasPermission("chatprotection.staff") || $player->isOp()) {
                $player->sendMessage($formattedMessage);
            }
        }
    }

    public function onDisable(): void {
        $this->getLogger()->info("ChatProtection Disabled");
    }
}
