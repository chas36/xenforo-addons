-- SQL для регистрации class extensions вручную
-- Выполнить только если development mode недоступен

-- 1. Расширение для XF\Entity\Poll
INSERT INTO xf_class_extension (from_class, to_class, execute_order, active, addon_id)
VALUES ('XF\\Entity\\Poll', 'Alebarda\\RankedPoll\\XF\\Entity\\Poll', 10, 1, 'Alebarda/RankedPoll');

-- 2. Расширение для XF\Repository\PollRepository
INSERT INTO xf_class_extension (from_class, to_class, execute_order, active, addon_id)
VALUES ('XF\\Repository\\PollRepository', 'Alebarda\\RankedPoll\\XF\\Repository\\PollRepository', 10, 1, 'Alebarda/RankedPoll');

-- 3. Расширение для XF\Pub\Controller\Poll
INSERT INTO xf_class_extension (from_class, to_class, execute_order, active, addon_id)
VALUES ('XF\\Pub\\Controller\\Poll', 'Alebarda\\RankedPoll\\XF\\Pub\\Controller\\Poll', 10, 1, 'Alebarda/RankedPoll');

-- После выполнения обязательно пересобрать кеш:
-- php cmd.php xf-rebuild:caches
