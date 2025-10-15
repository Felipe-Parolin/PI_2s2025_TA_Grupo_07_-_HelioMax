-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 15/10/2025 às 23:25
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

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
-- Estrutura para tabela `avaliacao`
--

CREATE TABLE `avaliacao` (
  `ID_AVALIACAO` int(11) NOT NULL,
  `COMENTARIO` varchar(200) DEFAULT NULL,
  `NOTA` tinyint(4) NOT NULL,
  `DATA_AVALIACAO` datetime NOT NULL,
  `FK_ID_USUARIO` int(11) NOT NULL,
  `FK_PONTO_CARRRGAMENTO` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `bairro`
--

CREATE TABLE `bairro` (
  `ID_BAIRRO` int(11) NOT NULL,
  `NOME` varchar(255) NOT NULL,
  `FK_CIDADE` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `bairro`
--

INSERT INTO `bairro` (`ID_BAIRRO`, `NOME`, `FK_CIDADE`) VALUES
(14, 'Centro', 12),
(1, 'Jardim Universitario ', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `cep`
--

CREATE TABLE `cep` (
  `ID_CEP` int(11) NOT NULL,
  `LOGRADOURO` varchar(255) NOT NULL,
  `FK_BAIRRO` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cep`
--

INSERT INTO `cep` (`ID_CEP`, `LOGRADOURO`, `FK_BAIRRO`) VALUES
(1, 'Av. Maximiliano Baruto', 1),
(13610100, 'Rafael de Barros', 14);

-- --------------------------------------------------------

--
-- Estrutura para tabela `cidade`
--

CREATE TABLE `cidade` (
  `ID_CIDADE` int(11) NOT NULL,
  `NOME` varchar(50) NOT NULL,
  `FK_ESTADO` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cidade`
--

INSERT INTO `cidade` (`ID_CIDADE`, `NOME`, `FK_ESTADO`) VALUES
(1, 'ARARAS', 1),
(12, 'Leme', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `conector`
--

CREATE TABLE `conector` (
  `ID_CONECTOR` int(11) NOT NULL,
  `NOME` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cor`
--

CREATE TABLE `cor` (
  `ID_COR` int(11) NOT NULL,
  `NOME` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cor`
--

INSERT INTO `cor` (`ID_COR`, `NOME`) VALUES
(5, 'Branco'),
(2, 'Cinza'),
(1, 'Preto');

-- --------------------------------------------------------

--
-- Estrutura para tabela `estado`
--

CREATE TABLE `estado` (
  `ID_ESTADO` int(11) NOT NULL,
  `UF` char(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `estado`
--

INSERT INTO `estado` (`ID_ESTADO`, `UF`) VALUES
(1, 'SP');

-- --------------------------------------------------------

--
-- Estrutura para tabela `marca`
--

CREATE TABLE `marca` (
  `ID_MARCA` int(11) NOT NULL,
  `NOME` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelo`
--

CREATE TABLE `modelo` (
  `ID_MODELO` int(11) NOT NULL,
  `FK_MARCA` int(11) NOT NULL,
  `NOME` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `parada_rota`
--

CREATE TABLE `parada_rota` (
  `ID_PARADA` int(11) NOT NULL,
  `DESCRICAO` varchar(100) NOT NULL,
  `NUMERO_RESIDENCIA` varchar(10) NOT NULL,
  `NOME` varchar(100) NOT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) DEFAULT NULL,
  `FK_ID_CEP` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ponto_carregamento`
--

CREATE TABLE `ponto_carregamento` (
  `ID_PONTO` int(11) NOT NULL,
  `LOCALIZACAO` int(11) NOT NULL,
  `VALOR_KWH` decimal(18,6) NOT NULL,
  `FK_STATUS_PONTO` int(11) NOT NULL,
  `FK_ID_USUARIO_CADASTRO` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ponto_favorito`
--

CREATE TABLE `ponto_favorito` (
  `ID_PONTO_INTERESSE` int(11) NOT NULL,
  `NOME` varchar(100) NOT NULL,
  `DESCRICAO` varchar(100) NOT NULL,
  `NUMERO_RESIDENCIA` varchar(10) NOT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) DEFAULT NULL,
  `FK_ID_CEP` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `rota`
--

CREATE TABLE `rota` (
  `ID_ROTA` int(11) NOT NULL,
  `DESCRICAO` varchar(100) NOT NULL,
  `NUMERO_RESIDENCIA` varchar(10) NOT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) NOT NULL,
  `TEMPO_MEDIO` time DEFAULT NULL,
  `FK_ID_CEP_INICIO` int(11) NOT NULL,
  `FK_ID_CEP_DESTINO` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `rota_veiculo`
--

CREATE TABLE `rota_veiculo` (
  `FK_VEICULO_ID_VEICULO` int(11) DEFAULT NULL,
  `FK_ROTA_ID_ROTA` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `status_ponto`
--

CREATE TABLE `status_ponto` (
  `ID_STATUS_PONTO` int(11) NOT NULL,
  `DESCRICAO` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `status_ponto`
--

INSERT INTO `status_ponto` (`ID_STATUS_PONTO`, `DESCRICAO`) VALUES
(1, 'Ativo'),
(2, 'Inativo'),
(3, 'Em Manutenção');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuario`
--

INSERT INTO `usuario` (`ID_USER`, `NOME`, `CPF`, `EMAIL`, `SENHA`, `TIPO_USUARIO`, `NUMERO_RESIDENCIA`, `COMPLEMENTO_ENDERECO`, `FK_ID_CEP`) VALUES
(1, 'Matheus', '12345678911', 'matheus@adm.com', '$2y$10$qJplYnDHOmHyHgzfae5JNOyZqKmKRdq6jtbZZ/ue56wN848192qcO', 0, '500', 'FHO', 1),
(2, 'Parolin', '12345678912', 'parolin@adm.com', '$2y$10$PoIVZk6MIlwkcGyHhvRAju1ypK457SmalylF2O/GYcZgKIENklnIy', 0, '500', 'FHO', 1),
(3, 'Chico', '12345678913', 'chico@adm.com', '$2y$10$Oqnjc8C3rJQPNJTk4sU8AO1XJEkT09KpSnPR7DZp/tPjR/haiJWuW', 0, '500', 'FHO', 1),
(4, 'Moi', '12345678914', 'moi@adm.com', '$2y$10$Du0Ozp2dgdJV2jTFmcYiou6KgMN75gfvLRAelRZlWaz2TDhi07Zdq', 0, '500', 'FHO', 1),
(5, 'Eduardo', '12345678915', 'eduardo@adm.com', '$2y$10$520Frj1dwi5jx1j3qEuM0OZbA0wcpBGehljJzvRy4xjslsE.Hfqxe', 0, '500', 'FHO', 1),
(6, 'Rafael', '12345678916', 'rafael@adm.com', '$2y$10$1DRRM8Nx3RTngb6lOlf0CeLYhjBr285c23EuvkMDTp/okcYT0zzIq', 0, '500', 'FHO', 1),
(11, 'Administrador', '11144477735', 'master@adm.com', '$2y$10$xsXu18NSMhEgUmiG26OVdOBV9IdPAuU6FThAxeXXO1nNmeX6MCgNe', 1, '1013', 'Pavan Tintas', 13610100);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_ponto_favorito`
--

CREATE TABLE `usuario_ponto_favorito` (
  `FK_USUARIO_ID_USER` int(11) DEFAULT NULL,
  `FK_PONTOS_FAV_ID_PONTO_INTERESSE` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `veiculo`
--

CREATE TABLE `veiculo` (
  `ID_VEICULO` int(11) NOT NULL,
  `MODELO` int(11) NOT NULL,
  `ANO_FAB` decimal(18,6) NOT NULL,
  `FK_CONECTOR` int(11) NOT NULL,
  `PLACA` varchar(10) NOT NULL,
  `FK_COR` int(11) NOT NULL,
  `FK_USUARIO_ID_USER` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `veiculo_parada_rota`
--

CREATE TABLE `veiculo_parada_rota` (
  `FK_VEICULO` int(11) DEFAULT NULL,
  `FK_PARADA_ROTA_ID_PARADA` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `veiculo_ponto_carregamento`
--

CREATE TABLE `veiculo_ponto_carregamento` (
  `FK_PONTO_CARREGAMENTO_ID_PONTO` int(11) DEFAULT NULL,
  `FK_VEICULO` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  ADD PRIMARY KEY (`ID_AVALIACAO`),
  ADD KEY `FK_AVALIACAO_2` (`FK_PONTO_CARRRGAMENTO`),
  ADD KEY `FK_AVALIACAO_3` (`FK_ID_USUARIO`);

--
-- Índices de tabela `bairro`
--
ALTER TABLE `bairro`
  ADD PRIMARY KEY (`ID_BAIRRO`),
  ADD UNIQUE KEY `NOME` (`NOME`,`FK_CIDADE`),
  ADD KEY `FK_BAIRRO_2` (`FK_CIDADE`);

--
-- Índices de tabela `cep`
--
ALTER TABLE `cep`
  ADD PRIMARY KEY (`ID_CEP`),
  ADD UNIQUE KEY `LOGRADOURO` (`LOGRADOURO`,`FK_BAIRRO`),
  ADD KEY `FK_CEP_2` (`FK_BAIRRO`);

--
-- Índices de tabela `cidade`
--
ALTER TABLE `cidade`
  ADD PRIMARY KEY (`ID_CIDADE`),
  ADD KEY `FK_CIDADE_2` (`FK_ESTADO`);

--
-- Índices de tabela `conector`
--
ALTER TABLE `conector`
  ADD PRIMARY KEY (`ID_CONECTOR`),
  ADD UNIQUE KEY `NOME` (`NOME`);

--
-- Índices de tabela `cor`
--
ALTER TABLE `cor`
  ADD PRIMARY KEY (`ID_COR`),
  ADD UNIQUE KEY `NOME` (`NOME`);

--
-- Índices de tabela `estado`
--
ALTER TABLE `estado`
  ADD PRIMARY KEY (`ID_ESTADO`),
  ADD UNIQUE KEY `UF` (`UF`);

--
-- Índices de tabela `marca`
--
ALTER TABLE `marca`
  ADD PRIMARY KEY (`ID_MARCA`);

--
-- Índices de tabela `modelo`
--
ALTER TABLE `modelo`
  ADD PRIMARY KEY (`ID_MODELO`),
  ADD KEY `FK_MODELO_2` (`FK_MARCA`);

--
-- Índices de tabela `parada_rota`
--
ALTER TABLE `parada_rota`
  ADD PRIMARY KEY (`ID_PARADA`),
  ADD KEY `FK_PARADA_ROTA_2` (`FK_ID_CEP`);

--
-- Índices de tabela `ponto_carregamento`
--
ALTER TABLE `ponto_carregamento`
  ADD PRIMARY KEY (`ID_PONTO`),
  ADD KEY `FK_PONTO_CARREGAMENTO_2` (`LOCALIZACAO`),
  ADD KEY `FK_PONTO_CARREGAMENTO_3` (`FK_STATUS_PONTO`),
  ADD KEY `fk_usuario_cadastro` (`FK_ID_USUARIO_CADASTRO`);

--
-- Índices de tabela `ponto_favorito`
--
ALTER TABLE `ponto_favorito`
  ADD PRIMARY KEY (`ID_PONTO_INTERESSE`),
  ADD KEY `FK_PONTO_FAVORITO_2` (`FK_ID_CEP`);

--
-- Índices de tabela `rota`
--
ALTER TABLE `rota`
  ADD PRIMARY KEY (`ID_ROTA`),
  ADD KEY `FK_ROTA_2` (`FK_ID_CEP_INICIO`),
  ADD KEY `FK_ROTA_3` (`FK_ID_CEP_DESTINO`);

--
-- Índices de tabela `rota_veiculo`
--
ALTER TABLE `rota_veiculo`
  ADD KEY `FK_ROTA_VEICULO_1` (`FK_VEICULO_ID_VEICULO`),
  ADD KEY `FK_ROTA_VEICULO_2` (`FK_ROTA_ID_ROTA`);

--
-- Índices de tabela `status_ponto`
--
ALTER TABLE `status_ponto`
  ADD PRIMARY KEY (`ID_STATUS_PONTO`);

--
-- Índices de tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`ID_USER`),
  ADD UNIQUE KEY `CPF` (`CPF`,`EMAIL`),
  ADD UNIQUE KEY `NOME` (`NOME`,`CPF`),
  ADD KEY `FK_USUARIO_2` (`FK_ID_CEP`);

--
-- Índices de tabela `usuario_ponto_favorito`
--
ALTER TABLE `usuario_ponto_favorito`
  ADD KEY `FK_USUARIO_PONTO_FAVORITO_1` (`FK_USUARIO_ID_USER`),
  ADD KEY `FK_USUARIO_PONTO_FAVORITO_2` (`FK_PONTOS_FAV_ID_PONTO_INTERESSE`);

--
-- Índices de tabela `veiculo`
--
ALTER TABLE `veiculo`
  ADD PRIMARY KEY (`ID_VEICULO`),
  ADD UNIQUE KEY `PLACA` (`PLACA`),
  ADD KEY `FK_VEICULO_2` (`FK_USUARIO_ID_USER`),
  ADD KEY `FK_VEICULO_3` (`FK_CONECTOR`),
  ADD KEY `FK_VEICULO_4` (`FK_COR`),
  ADD KEY `FK_VEICULO_5` (`MODELO`);

--
-- Índices de tabela `veiculo_parada_rota`
--
ALTER TABLE `veiculo_parada_rota`
  ADD KEY `FK_VEICULO_PARADA_ROTA_1` (`FK_VEICULO`),
  ADD KEY `FK_VEICULO_PARADA_ROTA_2` (`FK_PARADA_ROTA_ID_PARADA`);

--
-- Índices de tabela `veiculo_ponto_carregamento`
--
ALTER TABLE `veiculo_ponto_carregamento`
  ADD KEY `FK_VEICULO_PONTO_CARREGAMENTO_1` (`FK_PONTO_CARREGAMENTO_ID_PONTO`),
  ADD KEY `FK_VEICULO_PONTO_CARREGAMENTO_2` (`FK_VEICULO`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  MODIFY `ID_AVALIACAO` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `bairro`
--
ALTER TABLE `bairro`
  MODIFY `ID_BAIRRO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `cep`
--
ALTER TABLE `cep`
  MODIFY `ID_CEP` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13610101;

--
-- AUTO_INCREMENT de tabela `cidade`
--
ALTER TABLE `cidade`
  MODIFY `ID_CIDADE` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `conector`
--
ALTER TABLE `conector`
  MODIFY `ID_CONECTOR` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `cor`
--
ALTER TABLE `cor`
  MODIFY `ID_COR` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `estado`
--
ALTER TABLE `estado`
  MODIFY `ID_ESTADO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `marca`
--
ALTER TABLE `marca`
  MODIFY `ID_MARCA` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `modelo`
--
ALTER TABLE `modelo`
  MODIFY `ID_MODELO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `parada_rota`
--
ALTER TABLE `parada_rota`
  MODIFY `ID_PARADA` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ponto_carregamento`
--
ALTER TABLE `ponto_carregamento`
  MODIFY `ID_PONTO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `ponto_favorito`
--
ALTER TABLE `ponto_favorito`
  MODIFY `ID_PONTO_INTERESSE` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `ID_USER` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `veiculo`
--
ALTER TABLE `veiculo`
  MODIFY `ID_VEICULO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `avaliacao`
--
ALTER TABLE `avaliacao`
  ADD CONSTRAINT `FK_AVALIACAO_2` FOREIGN KEY (`FK_PONTO_CARRRGAMENTO`) REFERENCES `ponto_carregamento` (`ID_PONTO`),
  ADD CONSTRAINT `FK_AVALIACAO_3` FOREIGN KEY (`FK_ID_USUARIO`) REFERENCES `usuario` (`ID_USER`);

--
-- Restrições para tabelas `bairro`
--
ALTER TABLE `bairro`
  ADD CONSTRAINT `FK_BAIRRO_2` FOREIGN KEY (`FK_CIDADE`) REFERENCES `cidade` (`ID_CIDADE`);

--
-- Restrições para tabelas `cep`
--
ALTER TABLE `cep`
  ADD CONSTRAINT `FK_CEP_2` FOREIGN KEY (`FK_BAIRRO`) REFERENCES `bairro` (`ID_BAIRRO`);

--
-- Restrições para tabelas `cidade`
--
ALTER TABLE `cidade`
  ADD CONSTRAINT `FK_CIDADE_2` FOREIGN KEY (`FK_ESTADO`) REFERENCES `estado` (`ID_ESTADO`);

--
-- Restrições para tabelas `modelo`
--
ALTER TABLE `modelo`
  ADD CONSTRAINT `FK_MODELO_2` FOREIGN KEY (`FK_MARCA`) REFERENCES `marca` (`ID_MARCA`);

--
-- Restrições para tabelas `parada_rota`
--
ALTER TABLE `parada_rota`
  ADD CONSTRAINT `FK_PARADA_ROTA_2` FOREIGN KEY (`FK_ID_CEP`) REFERENCES `cep` (`ID_CEP`);

--
-- Restrições para tabelas `ponto_carregamento`
--
ALTER TABLE `ponto_carregamento`
  ADD CONSTRAINT `FK_PONTO_CARREGAMENTO_2` FOREIGN KEY (`LOCALIZACAO`) REFERENCES `cep` (`ID_CEP`),
  ADD CONSTRAINT `FK_PONTO_CARREGAMENTO_3` FOREIGN KEY (`FK_STATUS_PONTO`) REFERENCES `status_ponto` (`ID_STATUS_PONTO`),
  ADD CONSTRAINT `fk_usuario_cadastro` FOREIGN KEY (`FK_ID_USUARIO_CADASTRO`) REFERENCES `usuario` (`ID_USER`);

--
-- Restrições para tabelas `ponto_favorito`
--
ALTER TABLE `ponto_favorito`
  ADD CONSTRAINT `FK_PONTO_FAVORITO_2` FOREIGN KEY (`FK_ID_CEP`) REFERENCES `cep` (`ID_CEP`);

--
-- Restrições para tabelas `rota`
--
ALTER TABLE `rota`
  ADD CONSTRAINT `FK_ROTA_2` FOREIGN KEY (`FK_ID_CEP_INICIO`) REFERENCES `cep` (`ID_CEP`),
  ADD CONSTRAINT `FK_ROTA_3` FOREIGN KEY (`FK_ID_CEP_DESTINO`) REFERENCES `cep` (`ID_CEP`);

--
-- Restrições para tabelas `rota_veiculo`
--
ALTER TABLE `rota_veiculo`
  ADD CONSTRAINT `FK_ROTA_VEICULO_1` FOREIGN KEY (`FK_VEICULO_ID_VEICULO`) REFERENCES `veiculo` (`ID_VEICULO`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_ROTA_VEICULO_2` FOREIGN KEY (`FK_ROTA_ID_ROTA`) REFERENCES `rota` (`ID_ROTA`) ON DELETE SET NULL;

--
-- Restrições para tabelas `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `FK_USUARIO_2` FOREIGN KEY (`FK_ID_CEP`) REFERENCES `cep` (`ID_CEP`);

--
-- Restrições para tabelas `usuario_ponto_favorito`
--
ALTER TABLE `usuario_ponto_favorito`
  ADD CONSTRAINT `FK_USUARIO_PONTO_FAVORITO_1` FOREIGN KEY (`FK_USUARIO_ID_USER`) REFERENCES `usuario` (`ID_USER`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_USUARIO_PONTO_FAVORITO_2` FOREIGN KEY (`FK_PONTOS_FAV_ID_PONTO_INTERESSE`) REFERENCES `ponto_favorito` (`ID_PONTO_INTERESSE`) ON DELETE SET NULL;

--
-- Restrições para tabelas `veiculo`
--
ALTER TABLE `veiculo`
  ADD CONSTRAINT `FK_VEICULO_2` FOREIGN KEY (`FK_USUARIO_ID_USER`) REFERENCES `usuario` (`ID_USER`),
  ADD CONSTRAINT `FK_VEICULO_3` FOREIGN KEY (`FK_CONECTOR`) REFERENCES `conector` (`ID_CONECTOR`),
  ADD CONSTRAINT `FK_VEICULO_4` FOREIGN KEY (`FK_COR`) REFERENCES `cor` (`ID_COR`),
  ADD CONSTRAINT `FK_VEICULO_5` FOREIGN KEY (`MODELO`) REFERENCES `modelo` (`ID_MODELO`);

--
-- Restrições para tabelas `veiculo_parada_rota`
--
ALTER TABLE `veiculo_parada_rota`
  ADD CONSTRAINT `FK_VEICULO_PARADA_ROTA_1` FOREIGN KEY (`FK_VEICULO`) REFERENCES `veiculo` (`ID_VEICULO`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_VEICULO_PARADA_ROTA_2` FOREIGN KEY (`FK_PARADA_ROTA_ID_PARADA`) REFERENCES `parada_rota` (`ID_PARADA`) ON DELETE SET NULL;

--
-- Restrições para tabelas `veiculo_ponto_carregamento`
--
ALTER TABLE `veiculo_ponto_carregamento`
  ADD CONSTRAINT `FK_VEICULO_PONTO_CARREGAMENTO_1` FOREIGN KEY (`FK_PONTO_CARREGAMENTO_ID_PONTO`) REFERENCES `ponto_carregamento` (`ID_PONTO`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_VEICULO_PONTO_CARREGAMENTO_2` FOREIGN KEY (`FK_VEICULO`) REFERENCES `veiculo` (`ID_VEICULO`) ON DELETE SET NULL;
--
-- Banco de dados: `phpmyadmin`
--
CREATE DATABASE IF NOT EXISTS `phpmyadmin` DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
USE `phpmyadmin`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__bookmark`
--

CREATE TABLE `pma__bookmark` (
  `id` int(10) UNSIGNED NOT NULL,
  `dbase` varchar(255) NOT NULL DEFAULT '',
  `user` varchar(255) NOT NULL DEFAULT '',
  `label` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `query` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Bookmarks';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__central_columns`
--

CREATE TABLE `pma__central_columns` (
  `db_name` varchar(64) NOT NULL,
  `col_name` varchar(64) NOT NULL,
  `col_type` varchar(64) NOT NULL,
  `col_length` text DEFAULT NULL,
  `col_collation` varchar(64) NOT NULL,
  `col_isNull` tinyint(1) NOT NULL,
  `col_extra` varchar(255) DEFAULT '',
  `col_default` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Central list of columns';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__column_info`
--

CREATE TABLE `pma__column_info` (
  `id` int(5) UNSIGNED NOT NULL,
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `column_name` varchar(64) NOT NULL DEFAULT '',
  `comment` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `mimetype` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `transformation` varchar(255) NOT NULL DEFAULT '',
  `transformation_options` varchar(255) NOT NULL DEFAULT '',
  `input_transformation` varchar(255) NOT NULL DEFAULT '',
  `input_transformation_options` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Column information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__designer_settings`
--

CREATE TABLE `pma__designer_settings` (
  `username` varchar(64) NOT NULL,
  `settings_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Settings related to Designer';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__export_templates`
--

CREATE TABLE `pma__export_templates` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `export_type` varchar(10) NOT NULL,
  `template_name` varchar(64) NOT NULL,
  `template_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved export templates';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__favorite`
--

CREATE TABLE `pma__favorite` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Favorite tables';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__history`
--

CREATE TABLE `pma__history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db` varchar(64) NOT NULL DEFAULT '',
  `table` varchar(64) NOT NULL DEFAULT '',
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp(),
  `sqlquery` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='SQL history for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__navigationhiding`
--

CREATE TABLE `pma__navigationhiding` (
  `username` varchar(64) NOT NULL,
  `item_name` varchar(64) NOT NULL,
  `item_type` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Hidden items of navigation tree';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__pdf_pages`
--

CREATE TABLE `pma__pdf_pages` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `page_nr` int(10) UNSIGNED NOT NULL,
  `page_descr` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='PDF relation pages for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__recent`
--

CREATE TABLE `pma__recent` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Recently accessed tables';

--
-- Despejando dados para a tabela `pma__recent`
--

INSERT INTO `pma__recent` (`username`, `tables`) VALUES
('root', '[{\"db\":\"heliomax\",\"table\":\"usuario\"},{\"db\":\"heliomax\",\"table\":\"veiculo\"},{\"db\":\"heliomax\",\"table\":\"cep\"},{\"db\":\"heliomax\",\"table\":\"ponto_carregamento\"}]');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__relation`
--

CREATE TABLE `pma__relation` (
  `master_db` varchar(64) NOT NULL DEFAULT '',
  `master_table` varchar(64) NOT NULL DEFAULT '',
  `master_field` varchar(64) NOT NULL DEFAULT '',
  `foreign_db` varchar(64) NOT NULL DEFAULT '',
  `foreign_table` varchar(64) NOT NULL DEFAULT '',
  `foreign_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Relation table';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__savedsearches`
--

CREATE TABLE `pma__savedsearches` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `search_name` varchar(64) NOT NULL DEFAULT '',
  `search_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved searches';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__table_coords`
--

CREATE TABLE `pma__table_coords` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `pdf_page_number` int(11) NOT NULL DEFAULT 0,
  `x` float UNSIGNED NOT NULL DEFAULT 0,
  `y` float UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table coordinates for phpMyAdmin PDF output';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__table_info`
--

CREATE TABLE `pma__table_info` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `display_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__table_uiprefs`
--

CREATE TABLE `pma__table_uiprefs` (
  `username` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `prefs` text NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Tables'' UI preferences';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__tracking`
--

CREATE TABLE `pma__tracking` (
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `version` int(10) UNSIGNED NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `schema_snapshot` text NOT NULL,
  `schema_sql` text DEFAULT NULL,
  `data_sql` longtext DEFAULT NULL,
  `tracking` set('UPDATE','REPLACE','INSERT','DELETE','TRUNCATE','CREATE DATABASE','ALTER DATABASE','DROP DATABASE','CREATE TABLE','ALTER TABLE','RENAME TABLE','DROP TABLE','CREATE INDEX','DROP INDEX','CREATE VIEW','ALTER VIEW','DROP VIEW') DEFAULT NULL,
  `tracking_active` int(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Database changes tracking for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__userconfig`
--

CREATE TABLE `pma__userconfig` (
  `username` varchar(64) NOT NULL,
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `config_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User preferences storage for phpMyAdmin';

--
-- Despejando dados para a tabela `pma__userconfig`
--

INSERT INTO `pma__userconfig` (`username`, `timevalue`, `config_data`) VALUES
('root', '2025-10-15 21:25:01', '{\"Console\\/Mode\":\"collapse\",\"lang\":\"pt_BR\",\"NavigationWidth\":220.5999755859375}');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__usergroups`
--

CREATE TABLE `pma__usergroups` (
  `usergroup` varchar(64) NOT NULL,
  `tab` varchar(64) NOT NULL,
  `allowed` enum('Y','N') NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User groups with configured menu items';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__users`
--

CREATE TABLE `pma__users` (
  `username` varchar(64) NOT NULL,
  `usergroup` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Users and their assignments to user groups';

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `pma__central_columns`
--
ALTER TABLE `pma__central_columns`
  ADD PRIMARY KEY (`db_name`,`col_name`);

--
-- Índices de tabela `pma__column_info`
--
ALTER TABLE `pma__column_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `db_name` (`db_name`,`table_name`,`column_name`);

--
-- Índices de tabela `pma__designer_settings`
--
ALTER TABLE `pma__designer_settings`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_user_type_template` (`username`,`export_type`,`template_name`);

--
-- Índices de tabela `pma__favorite`
--
ALTER TABLE `pma__favorite`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__history`
--
ALTER TABLE `pma__history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`,`db`,`table`,`timevalue`);

--
-- Índices de tabela `pma__navigationhiding`
--
ALTER TABLE `pma__navigationhiding`
  ADD PRIMARY KEY (`username`,`item_name`,`item_type`,`db_name`,`table_name`);

--
-- Índices de tabela `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  ADD PRIMARY KEY (`page_nr`),
  ADD KEY `db_name` (`db_name`);

--
-- Índices de tabela `pma__recent`
--
ALTER TABLE `pma__recent`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__relation`
--
ALTER TABLE `pma__relation`
  ADD PRIMARY KEY (`master_db`,`master_table`,`master_field`),
  ADD KEY `foreign_field` (`foreign_db`,`foreign_table`);

--
-- Índices de tabela `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_savedsearches_username_dbname` (`username`,`db_name`,`search_name`);

--
-- Índices de tabela `pma__table_coords`
--
ALTER TABLE `pma__table_coords`
  ADD PRIMARY KEY (`db_name`,`table_name`,`pdf_page_number`);

--
-- Índices de tabela `pma__table_info`
--
ALTER TABLE `pma__table_info`
  ADD PRIMARY KEY (`db_name`,`table_name`);

--
-- Índices de tabela `pma__table_uiprefs`
--
ALTER TABLE `pma__table_uiprefs`
  ADD PRIMARY KEY (`username`,`db_name`,`table_name`);

--
-- Índices de tabela `pma__tracking`
--
ALTER TABLE `pma__tracking`
  ADD PRIMARY KEY (`db_name`,`table_name`,`version`);

--
-- Índices de tabela `pma__userconfig`
--
ALTER TABLE `pma__userconfig`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__usergroups`
--
ALTER TABLE `pma__usergroups`
  ADD PRIMARY KEY (`usergroup`,`tab`,`allowed`);

--
-- Índices de tabela `pma__users`
--
ALTER TABLE `pma__users`
  ADD PRIMARY KEY (`username`,`usergroup`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__column_info`
--
ALTER TABLE `pma__column_info`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__history`
--
ALTER TABLE `pma__history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  MODIFY `page_nr` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- Banco de dados: `test`
--
CREATE DATABASE IF NOT EXISTS `test` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `test`;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;