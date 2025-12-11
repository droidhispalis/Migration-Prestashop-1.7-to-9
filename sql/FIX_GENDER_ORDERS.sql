-- Fix para error "Undefined array key 7" en pedidos
-- Este error ocurre cuando falta el id_gender en la tabla customer

-- 1. Asegurar que todos los customers tengan un id_gender válido
UPDATE `top_customer` 
SET `id_gender` = 1 
WHERE `id_gender` IS NULL OR `id_gender` = 0 OR `id_gender` NOT IN (1, 2);

-- 2. Verificar que existen los géneros en la tabla gender
INSERT IGNORE INTO `top_gender` (`id_gender`, `type`) VALUES 
(1, 0),
(2, 1);

-- 3. Verificar que existen las traducciones de género
INSERT IGNORE INTO `top_gender_lang` (`id_gender`, `id_lang`, `name`) VALUES 
(1, 1, 'Sr.'),
(1, 2, 'Mr.'),
(2, 1, 'Sra.'),
(2, 2, 'Mrs.');

-- 4. Asegurar que todos los customers en orders tengan gender
UPDATE `top_customer` c
INNER JOIN `top_orders` o ON o.id_customer = c.id_customer
SET c.`id_gender` = 1
WHERE c.`id_gender` IS NULL OR c.`id_gender` = 0;

-- 5. Corregir addresses que puedan estar relacionadas con orders
UPDATE `top_address` 
SET `id_gender` = 1 
WHERE (`id_gender` IS NULL OR `id_gender` = 0) 
AND `id_customer` IN (SELECT DISTINCT `id_customer` FROM `top_orders`);

-- Verificación
SELECT 'Customers sin género:' AS verificacion, COUNT(*) AS total 
FROM `top_customer` 
WHERE `id_gender` IS NULL OR `id_gender` = 0 OR `id_gender` NOT IN (SELECT `id_gender` FROM `top_gender`);

SELECT 'Géneros disponibles:' AS info;
SELECT g.id_gender, gl.name, g.type 
FROM `top_gender` g 
LEFT JOIN `top_gender_lang` gl ON g.id_gender = gl.id_gender AND gl.id_lang = 1;
