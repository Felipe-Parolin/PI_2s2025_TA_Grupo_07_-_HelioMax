-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 11-Nov-2025 às 02:23
-- Versão do servidor: 10.4.20-MariaDB
-- versão do PHP: 8.0.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `heliomax`
--
CREATE DATABASE IF NOT EXISTS `heliomax` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `heliomax`;

-- --------------------------------------------------------

--
-- Estrutura da tabela `avaliacao`
--

CREATE TABLE `avaliacao` (
  `ID_AVALIACAO` int(11) NOT NULL,
  `COMENTARIO` varchar(200) DEFAULT NULL,
  `NOTA` tinyint(4) NOT NULL,
  `DATA_AVALIACAO` datetime NOT NULL,
  `EDITADO` tinyint(1) NOT NULL DEFAULT 0,
  `FK_ID_USUARIO` int(11) NOT NULL,
  `FK_PONTO_CARRRGAMENTO` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `avaliacao`
--

INSERT INTO `avaliacao` (`ID_AVALIACAO`, `COMENTARIO`, `NOTA`, `DATA_AVALIACAO`, `EDITADO`, `FK_ID_USUARIO`, `FK_PONTO_CARRRGAMENTO`) VALUES
(2, '', 5, '2025-11-10 22:01:57', 0, 6, 35);

-- --------------------------------------------------------

--
-- Estrutura da tabela `bairro`
--

CREATE TABLE `bairro` (
  `ID_BAIRRO` int(11) NOT NULL,
  `NOME` varchar(255) NOT NULL,
  `FK_CIDADE` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `bairro`
--

INSERT INTO `bairro` (`ID_BAIRRO`, `NOME`, `FK_CIDADE`) VALUES
(22, '', 16),
(28, 'Brasil', 19),
(14, 'Centro', 12),
(27, 'Centro', 18),
(15, 'Jardim Nova Era', 12),
(1, 'Jardim Universitario ', 1),
(29, 'Loteamento Grossklauss', 17),
(25, 'Novo Horizonte', 12),
(20, 'Parque Residencial Itamaraty', 12),
(16, 'teste da silva', 13),
(21, 'Vila Bom Jesus', 12),
(26, 'Vila Bom Jesus', 17);

-- --------------------------------------------------------

--
-- Estrutura da tabela `cep`
--

CREATE TABLE `cep` (
  `ID_CEP` int(11) NOT NULL,
  `CEP` varchar(9) DEFAULT NULL,
  `LOGRADOURO` varchar(255) NOT NULL,
  `FK_BAIRRO` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `cep`
--

INSERT INTO `cep` (`ID_CEP`, `CEP`, `LOGRADOURO`, `FK_BAIRRO`) VALUES
(1, '1', 'Av. Maximiliano Baruto', 1),
(13610000, '13610000', 'Avenida Doutor Jambeiro Costa', 21),
(13610100, '13610100', 'Rafael de Barros', 14),
(13610119, '13610119', 'Rua Rafael Urban', 15),
(13611479, '13611479', 'teste', 15),
(13611480, '13611480', 'Av. Teste da Silva 3', 16),
(13617437, '13617437', 'Rua Professora Durvalina Cantinho', 22),
(13617515, '13617515', 'Rua Professora Durvalina Cantinho', 20),
(13617521, '29164', 'Rua Professora Durvalina Cantinho', 25),
(13617522, '13610-000', 'Avenida Doutor Jambeiro Costa', 26),
(13617523, '13631-010', 'Rua Siqueira Campos', 27),
(13617524, '75760-000', 'BR-050', 28),
(13617525, '13617-437', 'Rua Professora Durvalina Cantinho', 29);

-- --------------------------------------------------------

--
-- Estrutura da tabela `cidade`
--

CREATE TABLE `cidade` (
  `ID_CIDADE` int(11) NOT NULL,
  `NOME` varchar(50) NOT NULL,
  `FK_ESTADO` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `cidade`
--

INSERT INTO `cidade` (`ID_CIDADE`, `NOME`, `FK_ESTADO`) VALUES
(1, 'ARARAS', 1),
(12, 'Leme', 1),
(13, 'pira', 2),
(16, '', 5),
(17, 'Leme', 6),
(18, 'Pirassununga', 6),
(19, 'Cumari', 7);

-- --------------------------------------------------------

--
-- Estrutura da tabela `conector`
--

CREATE TABLE `conector` (
  `ID_CONECTOR` int(11) NOT NULL,
  `NOME` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `conector`
--

INSERT INTO `conector` (`ID_CONECTOR`, `NOME`) VALUES
(2, 'CCS Combo 2'),
(4, 'CHAdeMO'),
(3, 'Tipo 1 (SAE J1772)'),
(1, 'Tipo 2 (Mennekes)');

-- --------------------------------------------------------

--
-- Estrutura da tabela `cor`
--

CREATE TABLE `cor` (
  `ID_COR` int(11) NOT NULL,
  `NOME` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `cor`
--

INSERT INTO `cor` (`ID_COR`, `NOME`) VALUES
(8, 'Amarelo'),
(5, 'Azul'),
(2, 'Branco'),
(4, 'Cinza'),
(9, 'Laranja'),
(3, 'Prata'),
(1, 'Preto'),
(10, 'Roxo'),
(7, 'Verde'),
(6, 'Vermelho');

-- --------------------------------------------------------

--
-- Estrutura da tabela `estado`
--

CREATE TABLE `estado` (
  `ID_ESTADO` int(11) NOT NULL,
  `UF` char(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `estado`
--

INSERT INTO `estado` (`ID_ESTADO`, `UF`) VALUES
(5, ''),
(1, 'ES'),
(7, 'GO'),
(2, 'RR'),
(6, 'SP');

-- --------------------------------------------------------

--
-- Estrutura da tabela `marca`
--

CREATE TABLE `marca` (
  `ID_MARCA` int(11) NOT NULL,
  `NOME` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `marca`
--

INSERT INTO `marca` (`ID_MARCA`, `NOME`) VALUES
(1, 'Tesla'),
(2, 'BYD'),
(3, 'Volkswagen'),
(4, 'Renault'),
(5, 'Nissan'),
(6, 'Chevrolet'),
(7, 'Volvo'),
(8, 'BMW'),
(9, 'Audi'),
(10, 'Peugeot');

-- --------------------------------------------------------

--
-- Estrutura da tabela `modelo`
--

CREATE TABLE `modelo` (
  `ID_MODELO` int(11) NOT NULL,
  `FK_MARCA` int(11) NOT NULL,
  `NOME` varchar(255) NOT NULL,
  `CAPACIDADE_BATERIA` decimal(5,2) NOT NULL,
  `CONSUMO_MEDIO` decimal(4,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `modelo`
--

INSERT INTO `modelo` (`ID_MODELO`, `FK_MARCA`, `NOME`, `CAPACIDADE_BATERIA`, `CONSUMO_MEDIO`) VALUES
(1, 1, 'Model 3', '57.50', '14.00'),
(2, 1, 'Model Y', '75.00', '15.50'),
(3, 2, 'Dolphin', '44.90', '13.50'),
(4, 2, 'Seal', '82.50', '15.80'),
(5, 3, 'ID.4', '77.00', '16.00'),
(6, 3, 'e-up!', '36.80', '12.00'),
(7, 4, 'Zoe', '52.00', '13.50'),
(8, 4, 'Megane E-Tech', '60.00', '15.00'),
(9, 5, 'Leaf', '40.00', '15.00'),
(10, 5, 'Ariya', '87.00', '17.00'),
(11, 6, 'Bolt EV', '66.00', '15.70'),
(12, 6, 'Bolt EUV', '66.00', '16.50'),
(13, 7, 'XC40 Recharge', '78.00', '18.00'),
(14, 7, 'EX30', '64.00', '16.80'),
(15, 8, 'i4', '83.90', '17.00'),
(16, 8, 'iX', '111.50', '19.50'),
(17, 9, 'Q4 e-tron', '82.00', '17.50'),
(18, 9, 'Q8 e-tron', '114.00', '21.00'),
(19, 10, 'e-208', '50.00', '13.00'),
(20, 10, 'e-2008', '54.00', '14.00');

-- --------------------------------------------------------

--
-- Estrutura da tabela `parada_rota`
--

CREATE TABLE `parada_rota` (
  `ID_PARADA` int(11) NOT NULL,
  `DESCRICAO` varchar(100) NOT NULL,
  `NUMERO_RESIDENCIA` varchar(10) NOT NULL,
  `NOME` varchar(100) NOT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) DEFAULT NULL,
  `FK_ID_CEP` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura da tabela `ponto_carregamento`
--

CREATE TABLE `ponto_carregamento` (
  `ID_PONTO` int(11) NOT NULL,
  `CEP` varchar(9) DEFAULT NULL,
  `NUMERO` varchar(10) DEFAULT NULL,
  `COMPLEMENTO` varchar(100) DEFAULT NULL,
  `LOCALIZACAO` int(11) NOT NULL,
  `VALOR_KWH` decimal(18,6) NOT NULL,
  `FK_STATUS_PONTO` int(11) NOT NULL,
  `FK_ID_USUARIO_CADASTRO` int(11) NOT NULL,
  `LATITUDE` decimal(10,8) DEFAULT NULL COMMENT 'Latitude do ponto de carregamento (-90 a 90)',
  `LONGITUDE` decimal(11,8) DEFAULT NULL COMMENT 'Longitude do ponto de carregamento (-180 a 180)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `ponto_carregamento`
--

INSERT INTO `ponto_carregamento` (`ID_PONTO`, `CEP`, `NUMERO`, `COMPLEMENTO`, `LOCALIZACAO`, `VALOR_KWH`, `FK_STATUS_PONTO`, `FK_ID_USUARIO_CADASTRO`, `LATITUDE`, `LONGITUDE`) VALUES
(28, NULL, NULL, NULL, 13611480, '0.950000', 1, 11, NULL, NULL),
(30, '29164', '190', '', 13617521, '0.950000', 1, 11, '-20.21384750', '-40.23809890'),
(32, '13610-000', '984', '', 13617522, '0.970000', 1, 11, '-22.19594990', '-47.39100490'),
(33, '13631-010', '1923', '', 13617523, '0.950000', 1, 11, '-22.00037050', '-47.42571560'),
(34, '75760-000', '40', 'testes', 13617524, '0.970000', 1, 6, '-18.41918750', '-48.07418750'),
(35, '13617-437', '190', '', 13617525, '2.450000', 1, 6, '-22.18910620', '-47.37109090');

-- --------------------------------------------------------

--
-- Estrutura da tabela `ponto_favorito`
--

CREATE TABLE `ponto_favorito` (
  `ID_PONTO_INTERESSE` int(11) NOT NULL,
  `NOME` varchar(100) NOT NULL,
  `DESCRICAO` varchar(100) NOT NULL,
  `NUMERO_RESIDENCIA` varchar(10) NOT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) DEFAULT NULL,
  `LATITUDE` decimal(10,8) DEFAULT NULL COMMENT 'Latitude do ponto favorito (-90 a 90)',
  `LONGITUDE` decimal(11,8) DEFAULT NULL COMMENT 'Longitude do ponto favorito (-180 a 180)',
  `FK_ID_CEP` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `ponto_favorito`
--

INSERT INTO `ponto_favorito` (`ID_PONTO_INTERESSE`, `NOME`, `DESCRICAO`, `NUMERO_RESIDENCIA`, `COMPLEMENTO_ENDERECO`, `LATITUDE`, `LONGITUDE`, `FK_ID_CEP`) VALUES
(22, 'Casa', 'R. Rafael Urban - Jardim Nova Era, Leme - SP, 13610-119, Brasil', '40', NULL, '-22.19735940', '-47.37886980', 13610119),
(23, 'Trabalho', 'R. Profa. Durvalina Cantinho - Parque Res. Itamaraty, Leme - SP, 13617-515, Brasil', '190', NULL, '-22.18749420', '-47.36952290', 13617515);

-- --------------------------------------------------------

--
-- Estrutura da tabela `recuperacao_senha`
--

CREATE TABLE `recuperacao_senha` (
  `ID_RECUPERACAO` int(11) NOT NULL,
  `FK_ID_USUARIO` int(11) NOT NULL,
  `TOKEN` varchar(64) NOT NULL,
  `DATA_CRIACAO` datetime NOT NULL,
  `DATA_EXPIRACAO` datetime NOT NULL,
  `UTILIZADO` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `recuperacao_senha`
--

INSERT INTO `recuperacao_senha` (`ID_RECUPERACAO`, `FK_ID_USUARIO`, `TOKEN`, `DATA_CRIACAO`, `DATA_EXPIRACAO`, `UTILIZADO`) VALUES
(1, 6, 'd1a12e75ef041b18b11d1bae86fa4aa68bb9391fbf1b6561215c786cf021bcae', '2025-10-16 01:47:56', '2025-10-16 02:47:56', 1),
(2, 6, 'aed7b54bbbb5e0cff102cfd72ed0c72ae343447c990a4c313babc030303e8310', '2025-10-16 01:48:09', '2025-10-16 02:48:09', 1),
(3, 6, '7f3c600384e9272dc82b5962eee426cfa179cac33247e583ee97e31657882cf6', '2025-10-16 01:49:00', '2025-10-16 02:49:00', 1),
(4, 6, '8bf84b66594c0ea25d02a49ff43d01a3a83c7ef3c0c5891576d1a9423ef44951', '2025-10-16 01:50:17', '2025-10-16 02:50:17', 1),
(5, 6, '5e03523fd70bece9708c039831642da109ffa504458bac64f02fe8d8073fd5cd', '2025-10-16 01:52:55', '2025-10-16 02:52:55', 1),
(6, 6, '9aeb29354b7262913be39928eb1f3cd9f8ceea90d5a55d1ff6a16ce7d6e0b1ae', '2025-10-16 01:53:53', '2025-10-16 02:53:53', 1),
(7, 6, 'f92e3af40c23c0769d20719c9ba3125c317ac7954a319097f8c5eb629bff3a64', '2025-10-16 01:56:21', '2025-10-16 02:56:21', 1),
(8, 6, 'adcf260d68ea8d1767f2c7cc8a43c9a449052f3df1e3d54435436761f7cf38a6', '2025-10-16 02:05:17', '2025-10-16 03:05:17', 1),
(9, 6, '3f941d0cc4c790f32edd3b8c84e4a22638f8aa80b6b23f5a416abd8bcd6351be', '2025-10-16 02:05:27', '2025-10-16 03:05:27', 1),
(10, 6, '0224538950936d4b40da777c9666549f29ca7d20d824de577dd372d79667bc3a', '2025-10-16 02:05:39', '2025-10-16 03:05:39', 1),
(11, 12, '75a20cf71316184099c3e1ad2214d34cad922a05f507618a31e0e0af71f09b93', '2025-10-16 03:02:39', '2025-10-16 04:02:39', 1),
(12, 12, 'c9de71960585c6a60fce30575100f672c809e99ad06996f5605f6fa160544dfd', '2025-10-16 03:18:33', '2025-10-16 04:18:33', 1),
(13, 12, '155931cf19b3a6fbda8d4790beac4ca8fe88c0697edd32425ebcfa0283a2b405', '2025-10-16 03:18:58', '2025-10-16 04:18:58', 1),
(14, 12, '7578ab02bfbfd8c23a4a370f82c10f54239b99050a280078d820a616ad7d00a1', '2025-10-16 03:23:03', '2025-10-16 04:23:03', 1),
(15, 12, 'f3a85bc09630e580d4cdcfe85f96f82da45133f59bd3877e1b8e56f220902584', '2025-10-16 03:24:48', '2025-10-16 04:24:48', 1),
(16, 6, 'f7cb6d5adcd0343483981ce62b37f746e5a5d941105dc6378a93a363bdac0613', '2025-10-16 03:27:03', '2025-10-16 04:27:03', 1),
(17, 6, 'dcd41a0fb67b0ca59f357a0b885cfb0040eb2fb604eca9c912725f8184d87523', '2025-10-16 03:27:23', '2025-10-16 04:27:23', 1),
(18, 6, '73c0193b5577f869d03ac2ba459c890a4c046daba2c357909661a191e72fc8c0', '2025-10-16 03:27:45', '2025-10-16 04:27:45', 1),
(19, 13, '58d1b7e4227d77678d6a38070d5e5d8485df020b41683ae604b8196ae0b43b0f', '2025-10-16 03:29:38', '2025-10-16 04:29:38', 1),
(20, 6, '52e4abef2a2e2376d36b610fbf1c7462e881b68cdf804b8128ca789d5da6c9b3', '2025-10-16 03:35:23', '2025-10-16 04:35:23', 1),
(21, 12, 'c2e9893597cb0ab88428f39495746abef33c795a51458f33dd5167476d3b1f5f', '2025-10-16 03:35:35', '2025-10-16 04:35:35', 1),
(22, 12, '15715c68f9df5b1c0281f6273661d0d8a0038771c3563cebc8170d01d121199d', '2025-10-16 03:35:43', '2025-10-16 04:35:43', 0),
(23, 13, '203d7c61c6a96f3fcee8fdef692ec4c330b8d7eedf854e67b3b6059311b0df63', '2025-10-16 03:43:38', '2025-10-16 04:43:38', 1),
(24, 13, '3ca7d530a0143142c808a9d4389883677774d7c5e10b166c88ced5f4632b2786', '2025-10-16 03:44:30', '2025-10-16 04:44:30', 1),
(25, 6, '5857ffc2b2ec3bd735814ba87b29a7520e29e0203bc0b67ee4cc78c2fbc433e9', '2025-10-16 03:45:53', '2025-10-16 04:45:53', 0),
(26, 13, 'b1d8c1ea3e12ff39e1c755faab39f7bf0e93bfc01fce96cebee600f6f850ccb0', '2025-10-16 03:49:46', '2025-10-16 04:49:46', 1),
(27, 13, 'df0a3255ce2a0ac1d3f2a3d6777315d442c6301d94ee7864b29cab0dd7e9f752', '2025-11-02 16:53:36', '2025-11-02 17:53:36', 0);

-- --------------------------------------------------------

--
-- Estrutura da tabela `rota`
--

CREATE TABLE `rota` (
  `ID_ROTA` int(11) NOT NULL,
  `DESCRICAO` varchar(100) NOT NULL,
  `NUMERO_RESIDENCIA` varchar(10) NOT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) NOT NULL,
  `TEMPO_MEDIO` time DEFAULT NULL,
  `FK_ID_CEP_INICIO` int(11) NOT NULL,
  `FK_ID_CEP_DESTINO` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura da tabela `rota_veiculo`
--

CREATE TABLE `rota_veiculo` (
  `FK_VEICULO_ID_VEICULO` int(11) DEFAULT NULL,
  `FK_ROTA_ID_ROTA` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura da tabela `status_ponto`
--

CREATE TABLE `status_ponto` (
  `ID_STATUS_PONTO` int(11) NOT NULL,
  `DESCRICAO` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `status_ponto`
--

INSERT INTO `status_ponto` (`ID_STATUS_PONTO`, `DESCRICAO`) VALUES
(1, 'Ativo'),
(2, 'Inativo'),
(3, 'Em Manutenção');

-- --------------------------------------------------------

--
-- Estrutura da tabela `usuario`
--

CREATE TABLE `usuario` (
  `ID_USER` int(11) NOT NULL,
  `NOME` varchar(100) NOT NULL,
  `CPF` varchar(11) NOT NULL,
  `EMAIL` varchar(150) NOT NULL,
  `SENHA` varchar(255) NOT NULL,
  `TIPO_USUARIO` tinyint(4) NOT NULL,
  `NUMERO_RESIDENCIA` varchar(10) NOT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) DEFAULT NULL,
  `FK_ID_CEP` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `usuario`
--

INSERT INTO `usuario` (`ID_USER`, `NOME`, `CPF`, `EMAIL`, `SENHA`, `TIPO_USUARIO`, `NUMERO_RESIDENCIA`, `COMPLEMENTO_ENDERECO`, `FK_ID_CEP`) VALUES
(1, 'Matheus', '12345678911', 'matheus@adm.com', '$2y$10$JsXnUJHgFtBmqgM.0yHAZ.OxG6JJFbpJOs.WAs/eBaqH0vcImolki', 0, '500', 'FHO', 1),
(2, 'Parolin', '12345678912', 'parolin@adm.com', '$2y$10$1pYyiONP8tPdvW/Baigy2ObxWwOhX7SJpzk0LtimrlpIaJcnewH5.', 0, '500', 'FHO', 1),
(3, 'Chico', '12345678913', 'chico@adm.com', '$2y$10$iV0xKwHgwyCUGj6ab.H1fOqFRh3oTBYi3Wm/XcajFuS5Wp6RgRoo.', 0, '500', 'FHO', 1),
(4, 'Moi', '12345678914', 'moi@adm.com', '$2y$10$f9ARigLzKEPj8u3tZDVvge3bkPMkdQqrFBHi.11o.xXwauy8zjv/u', 0, '500', 'FHO', 1),
(5, 'Eduardo', '12345678915', 'eduardo@adm.com', '$2y$10$SMhSGRs9mnNyA8dfF3rJ1u3uhW6JouEzCOn988aLBUoVCLKgCI2Xa', 0, '500', 'FHO', 1),
(6, 'Rafael', '12345678916', 'rafael@adm.com', '$2y$10$plGRmIGgXl18qb9Fw8hU6.TmVHfdHUwY2lfr8R8Zc4eIHYjL7B082', 0, '500', 'FHO', 1),
(11, 'Administrador', '11144477735', 'master@adm.com', '$2y$10$LVYUDfqpb1lyStSNcSksp.vG1h16Q///RlWVhX1LZRsr/Qh5HPgpy', 1, '1013', 'Pavan Tintas', 13610100),
(12, 'Rafael', '49577562019', 'rafael.mantoan@alunos.fho.edu.br', '$2y$10$GM0Yd7do69.ZlYur.jVn5.DxiY.A5qjlVKndceIsVQvVuJJNCy3RW', 0, '50', 'aviao', 13611479),
(13, 'Rafael', '12043314050', 'rafaeldonizetemantoan@gmail.com', '$2y$10$XbFSJPrao5nzB.HZ4mSzJe3rVMtEnrk2O9qOgcDe1fxoEA8Y6iRmq', 0, '45', 'aviao', 13611479);

-- --------------------------------------------------------

--
-- Estrutura da tabela `usuario_ponto_favorito`
--

CREATE TABLE `usuario_ponto_favorito` (
  `FK_USUARIO_ID_USER` int(11) DEFAULT NULL,
  `FK_PONTOS_FAV_ID_PONTO_INTERESSE` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Extraindo dados da tabela `usuario_ponto_favorito`
--

INSERT INTO `usuario_ponto_favorito` (`FK_USUARIO_ID_USER`, `FK_PONTOS_FAV_ID_PONTO_INTERESSE`) VALUES
(12, 22),
(12, 23);

-- --------------------------------------------------------

--
-- Estrutura da tabela `veiculo`
--

CREATE TABLE `veiculo` (
  `ID_VEICULO` int(11) NOT NULL,
  `MODELO` int(11) NOT NULL,
  `ANO_FAB` decimal(18,6) NOT NULL,
  `FK_CONECTOR` int(11) NOT NULL,
  `PLACA` varchar(10) NOT NULL,
  `NIVEL_BATERIA` decimal(5,2) DEFAULT 0.00,
  `FK_COR` int(11) NOT NULL,
  `FK_USUARIO_ID_USER` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura da tabela `veiculo_parada_rota`
--

CREATE TABLE `veiculo_parada_rota` (
  `FK_VEICULO` int(11) DEFAULT NULL,
  `FK_PARADA_ROTA_ID_PARADA` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estrutura da tabela `veiculo_ponto_carregamento`
--

CREATE TABLE `veiculo_ponto_carregamento` (
  `FK_PONTO_CARREGAMENTO_ID_PONTO` int(11) DEFAULT NULL,
  `FK_VEICULO` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  ADD PRIMARY KEY (`ID_AVALIACAO`),
  ADD KEY `FK_AVALIACAO_2` (`FK_PONTO_CARRRGAMENTO`),
  ADD KEY `FK_AVALIACAO_3` (`FK_ID_USUARIO`);

--
-- Índices para tabela `bairro`
--
ALTER TABLE `bairro`
  ADD PRIMARY KEY (`ID_BAIRRO`),
  ADD UNIQUE KEY `NOME` (`NOME`,`FK_CIDADE`),
  ADD KEY `FK_BAIRRO_2` (`FK_CIDADE`);

--
-- Índices para tabela `cep`
--
ALTER TABLE `cep`
  ADD PRIMARY KEY (`ID_CEP`),
  ADD KEY `FK_CEP_2` (`FK_BAIRRO`);

--
-- Índices para tabela `cidade`
--
ALTER TABLE `cidade`
  ADD PRIMARY KEY (`ID_CIDADE`),
  ADD KEY `FK_CIDADE_2` (`FK_ESTADO`);

--
-- Índices para tabela `conector`
--
ALTER TABLE `conector`
  ADD PRIMARY KEY (`ID_CONECTOR`),
  ADD UNIQUE KEY `NOME` (`NOME`);

--
-- Índices para tabela `cor`
--
ALTER TABLE `cor`
  ADD PRIMARY KEY (`ID_COR`),
  ADD UNIQUE KEY `NOME` (`NOME`);

--
-- Índices para tabela `estado`
--
ALTER TABLE `estado`
  ADD PRIMARY KEY (`ID_ESTADO`),
  ADD UNIQUE KEY `UF` (`UF`);

--
-- Índices para tabela `marca`
--
ALTER TABLE `marca`
  ADD PRIMARY KEY (`ID_MARCA`);

--
-- Índices para tabela `modelo`
--
ALTER TABLE `modelo`
  ADD PRIMARY KEY (`ID_MODELO`),
  ADD KEY `FK_MODELO_2` (`FK_MARCA`);

--
-- Índices para tabela `parada_rota`
--
ALTER TABLE `parada_rota`
  ADD PRIMARY KEY (`ID_PARADA`),
  ADD KEY `FK_PARADA_ROTA_2` (`FK_ID_CEP`);

--
-- Índices para tabela `ponto_carregamento`
--
ALTER TABLE `ponto_carregamento`
  ADD PRIMARY KEY (`ID_PONTO`),
  ADD KEY `FK_PONTO_CARREGAMENTO_2` (`LOCALIZACAO`),
  ADD KEY `FK_PONTO_CARREGAMENTO_3` (`FK_STATUS_PONTO`),
  ADD KEY `fk_usuario_cadastro` (`FK_ID_USUARIO_CADASTRO`),
  ADD KEY `idx_coordenadas` (`LATITUDE`,`LONGITUDE`);

--
-- Índices para tabela `ponto_favorito`
--
ALTER TABLE `ponto_favorito`
  ADD PRIMARY KEY (`ID_PONTO_INTERESSE`),
  ADD KEY `FK_PONTO_FAVORITO_2` (`FK_ID_CEP`),
  ADD KEY `idx_coordenadas` (`LATITUDE`,`LONGITUDE`);

--
-- Índices para tabela `recuperacao_senha`
--
ALTER TABLE `recuperacao_senha`
  ADD PRIMARY KEY (`ID_RECUPERACAO`),
  ADD UNIQUE KEY `TOKEN` (`TOKEN`),
  ADD KEY `FK_RECUPERACAO_USUARIO` (`FK_ID_USUARIO`),
  ADD KEY `idx_token_valido` (`TOKEN`,`UTILIZADO`,`DATA_EXPIRACAO`);

--
-- Índices para tabela `rota`
--
ALTER TABLE `rota`
  ADD PRIMARY KEY (`ID_ROTA`),
  ADD KEY `FK_ROTA_2` (`FK_ID_CEP_INICIO`),
  ADD KEY `FK_ROTA_3` (`FK_ID_CEP_DESTINO`);

--
-- Índices para tabela `rota_veiculo`
--
ALTER TABLE `rota_veiculo`
  ADD KEY `FK_ROTA_VEICULO_1` (`FK_VEICULO_ID_VEICULO`),
  ADD KEY `FK_ROTA_VEICULO_2` (`FK_ROTA_ID_ROTA`);

--
-- Índices para tabela `status_ponto`
--
ALTER TABLE `status_ponto`
  ADD PRIMARY KEY (`ID_STATUS_PONTO`);

--
-- Índices para tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`ID_USER`),
  ADD UNIQUE KEY `CPF` (`CPF`,`EMAIL`),
  ADD UNIQUE KEY `NOME` (`NOME`,`CPF`),
  ADD KEY `FK_USUARIO_2` (`FK_ID_CEP`);

--
-- Índices para tabela `usuario_ponto_favorito`
--
ALTER TABLE `usuario_ponto_favorito`
  ADD KEY `FK_USUARIO_PONTO_FAVORITO_1` (`FK_USUARIO_ID_USER`),
  ADD KEY `FK_USUARIO_PONTO_FAVORITO_2` (`FK_PONTOS_FAV_ID_PONTO_INTERESSE`);

--
-- Índices para tabela `veiculo`
--
ALTER TABLE `veiculo`
  ADD PRIMARY KEY (`ID_VEICULO`),
  ADD UNIQUE KEY `PLACA` (`PLACA`),
  ADD KEY `FK_VEICULO_2` (`FK_USUARIO_ID_USER`),
  ADD KEY `FK_VEICULO_3` (`FK_CONECTOR`),
  ADD KEY `FK_VEICULO_4` (`FK_COR`),
  ADD KEY `FK_VEICULO_5` (`MODELO`);

--
-- Índices para tabela `veiculo_parada_rota`
--
ALTER TABLE `veiculo_parada_rota`
  ADD KEY `FK_VEICULO_PARADA_ROTA_1` (`FK_VEICULO`),
  ADD KEY `FK_VEICULO_PARADA_ROTA_2` (`FK_PARADA_ROTA_ID_PARADA`);

--
-- Índices para tabela `veiculo_ponto_carregamento`
--
ALTER TABLE `veiculo_ponto_carregamento`
  ADD KEY `FK_VEICULO_PONTO_CARREGAMENTO_1` (`FK_PONTO_CARREGAMENTO_ID_PONTO`),
  ADD KEY `FK_VEICULO_PONTO_CARREGAMENTO_2` (`FK_VEICULO`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  MODIFY `ID_AVALIACAO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `bairro`
--
ALTER TABLE `bairro`
  MODIFY `ID_BAIRRO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `cep`
--
ALTER TABLE `cep`
  MODIFY `ID_CEP` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13617526;

--
-- AUTO_INCREMENT de tabela `cidade`
--
ALTER TABLE `cidade`
  MODIFY `ID_CIDADE` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `conector`
--
ALTER TABLE `conector`
  MODIFY `ID_CONECTOR` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `cor`
--
ALTER TABLE `cor`
  MODIFY `ID_COR` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `estado`
--
ALTER TABLE `estado`
  MODIFY `ID_ESTADO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `marca`
--
ALTER TABLE `marca`
  MODIFY `ID_MARCA` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `modelo`
--
ALTER TABLE `modelo`
  MODIFY `ID_MODELO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de tabela `parada_rota`
--
ALTER TABLE `parada_rota`
  MODIFY `ID_PARADA` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ponto_carregamento`
--
ALTER TABLE `ponto_carregamento`
  MODIFY `ID_PONTO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `ponto_favorito`
--
ALTER TABLE `ponto_favorito`
  MODIFY `ID_PONTO_INTERESSE` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `recuperacao_senha`
--
ALTER TABLE `recuperacao_senha`
  MODIFY `ID_RECUPERACAO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `rota`
--
ALTER TABLE `rota`
  MODIFY `ID_ROTA` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `status_ponto`
--
ALTER TABLE `status_ponto`
  MODIFY `ID_STATUS_PONTO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `usuario`
--
ALTER TABLE `usuario`
  MODIFY `ID_USER` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `veiculo`
--
ALTER TABLE `veiculo`
  MODIFY `ID_VEICULO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  ADD CONSTRAINT `FK_AVALIACAO_2` FOREIGN KEY (`FK_PONTO_CARRRGAMENTO`) REFERENCES `ponto_carregamento` (`ID_PONTO`),
  ADD CONSTRAINT `FK_AVALIACAO_3` FOREIGN KEY (`FK_ID_USUARIO`) REFERENCES `usuario` (`ID_USER`);

--
-- Limitadores para a tabela `bairro`
--
ALTER TABLE `bairro`
  ADD CONSTRAINT `FK_BAIRRO_2` FOREIGN KEY (`FK_CIDADE`) REFERENCES `cidade` (`ID_CIDADE`);

--
-- Limitadores para a tabela `cep`
--
ALTER TABLE `cep`
  ADD CONSTRAINT `FK_CEP_2` FOREIGN KEY (`FK_BAIRRO`) REFERENCES `bairro` (`ID_BAIRRO`);

--
-- Limitadores para a tabela `cidade`
--
ALTER TABLE `cidade`
  ADD CONSTRAINT `FK_CIDADE_2` FOREIGN KEY (`FK_ESTADO`) REFERENCES `estado` (`ID_ESTADO`);

--
-- Limitadores para a tabela `modelo`
--
ALTER TABLE `modelo`
  ADD CONSTRAINT `FK_MODELO_2` FOREIGN KEY (`FK_MARCA`) REFERENCES `marca` (`ID_MARCA`);

--
-- Limitadores para a tabela `parada_rota`
--
ALTER TABLE `parada_rota`
  ADD CONSTRAINT `FK_PARADA_ROTA_2` FOREIGN KEY (`FK_ID_CEP`) REFERENCES `cep` (`ID_CEP`);

--
-- Limitadores para a tabela `ponto_carregamento`
--
ALTER TABLE `ponto_carregamento`
  ADD CONSTRAINT `FK_PONTO_CARREGAMENTO_2` FOREIGN KEY (`LOCALIZACAO`) REFERENCES `cep` (`ID_CEP`),
  ADD CONSTRAINT `FK_PONTO_CARREGAMENTO_3` FOREIGN KEY (`FK_STATUS_PONTO`) REFERENCES `status_ponto` (`ID_STATUS_PONTO`),
  ADD CONSTRAINT `fk_usuario_cadastro` FOREIGN KEY (`FK_ID_USUARIO_CADASTRO`) REFERENCES `usuario` (`ID_USER`);

--
-- Limitadores para a tabela `ponto_favorito`
--
ALTER TABLE `ponto_favorito`
  ADD CONSTRAINT `FK_PONTO_FAVORITO_2` FOREIGN KEY (`FK_ID_CEP`) REFERENCES `cep` (`ID_CEP`);

--
-- Limitadores para a tabela `recuperacao_senha`
--
ALTER TABLE `recuperacao_senha`
  ADD CONSTRAINT `FK_RECUPERACAO_USUARIO` FOREIGN KEY (`FK_ID_USUARIO`) REFERENCES `usuario` (`ID_USER`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `rota`
--
ALTER TABLE `rota`
  ADD CONSTRAINT `FK_ROTA_2` FOREIGN KEY (`FK_ID_CEP_INICIO`) REFERENCES `cep` (`ID_CEP`),
  ADD CONSTRAINT `FK_ROTA_3` FOREIGN KEY (`FK_ID_CEP_DESTINO`) REFERENCES `cep` (`ID_CEP`);

--
-- Limitadores para a tabela `rota_veiculo`
--
ALTER TABLE `rota_veiculo`
  ADD CONSTRAINT `FK_ROTA_VEICULO_1` FOREIGN KEY (`FK_VEICULO_ID_VEICULO`) REFERENCES `veiculo` (`ID_VEICULO`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_ROTA_VEICULO_2` FOREIGN KEY (`FK_ROTA_ID_ROTA`) REFERENCES `rota` (`ID_ROTA`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `FK_USUARIO_2` FOREIGN KEY (`FK_ID_CEP`) REFERENCES `cep` (`ID_CEP`);

--
-- Limitadores para a tabela `usuario_ponto_favorito`
--
ALTER TABLE `usuario_ponto_favorito`
  ADD CONSTRAINT `FK_USUARIO_PONTO_FAVORITO_1` FOREIGN KEY (`FK_USUARIO_ID_USER`) REFERENCES `usuario` (`ID_USER`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_USUARIO_PONTO_FAVORITO_2` FOREIGN KEY (`FK_PONTOS_FAV_ID_PONTO_INTERESSE`) REFERENCES `ponto_favorito` (`ID_PONTO_INTERESSE`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `veiculo`
--
ALTER TABLE `veiculo`
  ADD CONSTRAINT `FK_VEICULO_2` FOREIGN KEY (`FK_USUARIO_ID_USER`) REFERENCES `usuario` (`ID_USER`),
  ADD CONSTRAINT `FK_VEICULO_3` FOREIGN KEY (`FK_CONECTOR`) REFERENCES `conector` (`ID_CONECTOR`),
  ADD CONSTRAINT `FK_VEICULO_4` FOREIGN KEY (`FK_COR`) REFERENCES `cor` (`ID_COR`),
  ADD CONSTRAINT `FK_VEICULO_5` FOREIGN KEY (`MODELO`) REFERENCES `modelo` (`ID_MODELO`);

--
-- Limitadores para a tabela `veiculo_parada_rota`
--
ALTER TABLE `veiculo_parada_rota`
  ADD CONSTRAINT `FK_VEICULO_PARADA_ROTA_1` FOREIGN KEY (`FK_VEICULO`) REFERENCES `veiculo` (`ID_VEICULO`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_VEICULO_PARADA_ROTA_2` FOREIGN KEY (`FK_PARADA_ROTA_ID_PARADA`) REFERENCES `parada_rota` (`ID_PARADA`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `veiculo_ponto_carregamento`
--
ALTER TABLE `veiculo_ponto_carregamento`
  ADD CONSTRAINT `FK_VEICULO_PONTO_CARREGAMENTO_1` FOREIGN KEY (`FK_PONTO_CARREGAMENTO_ID_PONTO`) REFERENCES `ponto_carregamento` (`ID_PONTO`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_VEICULO_PONTO_CARREGAMENTO_2` FOREIGN KEY (`FK_VEICULO`) REFERENCES `veiculo` (`ID_VEICULO`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Adicionar tabela de histórico de rotas
CREATE TABLE IF NOT EXISTS `historico_rota` (
  `ID_HISTORICO` int(11) NOT NULL AUTO_INCREMENT,
  `FK_USUARIO` int(11) NOT NULL,
  `FK_VEICULO` int(11) DEFAULT NULL,
  `ORIGEM_LAT` decimal(10,8) NOT NULL,
  `ORIGEM_LNG` decimal(11,8) NOT NULL,
  `ORIGEM_ENDERECO` varchar(255) NOT NULL,
  `DESTINO_LAT` decimal(10,8) NOT NULL,
  `DESTINO_LNG` decimal(11,8) NOT NULL,
  `DESTINO_ENDERECO` varchar(255) NOT NULL,
  `DISTANCIA_TOTAL_KM` decimal(10,2) NOT NULL,
  `TEMPO_CONDUCAO_MIN` int(11) NOT NULL,
  `TEMPO_CARREGAMENTO_MIN` int(11) NOT NULL,
  `PARADAS_TOTAIS` int(11) NOT NULL DEFAULT 0,
  `ENERGIA_CONSUMIDA_KWH` decimal(10,2) NOT NULL,
  `CUSTO_TOTAL` decimal(10,2) NOT NULL,
  `CARGA_FINAL_PCT` decimal(5,2) NOT NULL,
  `DATA_SIMULACAO` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `MODO_OTIMISTA` tinyint(1) NOT NULL DEFAULT 0,
  `DADOS_PARADAS` longtext DEFAULT NULL COMMENT 'JSON com detalhes das paradas',
  `POLYLINE` longtext DEFAULT NULL COMMENT 'Polyline codificada da rota',
  PRIMARY KEY (`ID_HISTORICO`),
  KEY `FK_HISTORICO_USUARIO` (`FK_USUARIO`),
  KEY `FK_HISTORICO_VEICULO` (`FK_VEICULO`),
  KEY `idx_data_simulacao` (`DATA_SIMULACAO`),
  CONSTRAINT `FK_HISTORICO_USUARIO` FOREIGN KEY (`FK_USUARIO`) REFERENCES `usuario` (`ID_USER`) ON DELETE CASCADE,
  CONSTRAINT `FK_HISTORICO_VEICULO` FOREIGN KEY (`FK_VEICULO`) REFERENCES `veiculo` (`ID_VEICULO`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Histórico de rotas simuladas pelos usuários';