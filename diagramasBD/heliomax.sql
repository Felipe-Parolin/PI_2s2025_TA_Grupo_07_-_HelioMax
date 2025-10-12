-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 09/10/2025 às 01:28
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
  `NOTA` tinyint(4) DEFAULT NULL,
  `DATA_AVALIACAO` datetime DEFAULT NULL,
  `FK_ID_USUARIO` int(11) DEFAULT NULL,
  `FK_PONTO_CARRRGAMENTO` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `bairro`
--

CREATE TABLE `bairro` (
  `ID_BAIRRO` int(11) NOT NULL,
  `NOME` varchar(100) DEFAULT NULL,
  `FK_CIDADE` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cep`
--

CREATE TABLE `cep` (
  `ID_CEP` int(11) NOT NULL,
  `LOGRADOURO` varchar(50) DEFAULT NULL,
  `FK_BAIRRO` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- --------------------------------------------------------

--
-- Estrutura para tabela `cidade`
--

CREATE TABLE `cidade` (
  `ID_CIDADE` int(11) NOT NULL,
  `NOME` varchar(50) DEFAULT NULL,
  `FK_ESTADO` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `conector`
--

CREATE TABLE `conector` (
  `ID_CONECTOR` int(11) NOT NULL,
  `NOME` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `conector`
--

INSERT INTO `conector` (`ID_CONECTOR`, `NOME`) VALUES
(1, 'Tipo 2');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cor`
--

CREATE TABLE `cor` (
  `ID_COR` int(11) NOT NULL,
  `NOME` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cor`
--

INSERT INTO `cor` (`ID_COR`, `NOME`) VALUES
(1, 'Preto');

-- --------------------------------------------------------

--
-- Estrutura para tabela `estado`
--

CREATE TABLE `estado` (
  `ID_ESTADO` int(11) NOT NULL,
  `UF` char(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `marca`
--

CREATE TABLE `marca` (
  `ID_MARCA` int(11) NOT NULL,
  `NOME` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `marca`
--

INSERT INTO `marca` (`ID_MARCA`, `NOME`) VALUES
(1, 'Tesla');

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelo`
--

CREATE TABLE `modelo` (
  `ID_MODELO` int(11) NOT NULL,
  `FK_MARCA` int(11) DEFAULT NULL,
  `NOME` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `modelo`
--

INSERT INTO `modelo` (`ID_MODELO`, `FK_MARCA`, `NOME`) VALUES
(1, 1, 'Model S'),
(2, 1, 'Model S');

-- --------------------------------------------------------

--
-- Estrutura para tabela `parada_rota`
--

CREATE TABLE `parada_rota` (
  `ID_PARADA` int(11) NOT NULL,
  `DESCRICAO` varchar(100) DEFAULT NULL,
  `NUMERO_RESIDENCIA` varchar(10) DEFAULT NULL,
  `NOME` varchar(100) DEFAULT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) DEFAULT NULL,
  `FK_ID_CEP` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ponto_carregamento`
--

CREATE TABLE `ponto_carregamento` (
  `ID_PONTO` int(11) NOT NULL,
  `LOCALIZACAO` int(11) DEFAULT NULL,
  `VALOR_KWH` decimal(18,6) DEFAULT NULL,
  `FK_STATUS_PONTO` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ponto_favorito`
--

CREATE TABLE `ponto_favorito` (
  `ID_PONTO_INTERESSE` int(11) NOT NULL,
  `NOME` varchar(100) DEFAULT NULL,
  `DESCRICAO` varchar(100) DEFAULT NULL,
  `NUMERO_RESIDENCIA` varchar(10) DEFAULT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) DEFAULT NULL,
  `FK_ID_CEP` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `rota`
--

CREATE TABLE `rota` (
  `ID_ROTA` int(11) NOT NULL,
  `DESCRICAO` varchar(100) DEFAULT NULL,
  `NUMERO_RESIDENCIA` varchar(10) DEFAULT NULL,
  `COMPLEMENTO_ENDERECO` varchar(100) DEFAULT NULL,
  `TEMPO_MEDIO` time DEFAULT NULL,
  `FK_ID_CEP_INICIO` int(11) DEFAULT NULL,
  `FK_ID_CEP_DESTINO` int(11) DEFAULT NULL
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
  `DESCRICAO` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
--

CREATE TABLE `usuario` (
  `ID_USER` int(11) NOT NULL,
  `NOME` varchar(100) DEFAULT NULL,
  `CPF` varchar(11) DEFAULT NULL,
  `EMAIL` varchar(150) DEFAULT NULL,
  `SENHA` varchar(50) DEFAULT NULL,
  `TIPO_USUARIO` tinyint(4) DEFAULT NULL,
  `NUMERO_RESIDENCIA` varchar(10) DEFAULT NULL,
  `COMPLEMENTO_ENDERENCO` varchar(100) DEFAULT NULL,
  `FK_ID_CEP` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuario`
--

INSERT INTO `usuario` (`ID_USER`, `NOME`, `CPF`, `EMAIL`, `SENHA`, `TIPO_USUARIO`, `NUMERO_RESIDENCIA`, `COMPLEMENTO_ENDERENCO`, `FK_ID_CEP`) VALUES
(1, 'Matheus Araujo', '12345678900', 'matheus@email.com', '123456', 1, NULL, NULL, NULL);

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
  `MODELO` int(11) DEFAULT NULL,
  `ANO_FAB` decimal(18,6) DEFAULT NULL,
  `FK_CONECTOR` int(11) DEFAULT NULL,
  `PLACA` varchar(10) DEFAULT NULL,
  `FK_COR` int(11) DEFAULT NULL,
  `FK_USUARIO_ID_USER` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `veiculo`
--

INSERT INTO `veiculo` (`ID_VEICULO`, `MODELO`, `ANO_FAB`, `FK_CONECTOR`, `PLACA`, `FK_COR`, `FK_USUARIO_ID_USER`) VALUES
(5, 1, 2022.000000, 1, 'ABC1D23', 1, 1);

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
  ADD KEY `FK_BAIRRO_2` (`FK_CIDADE`);

--
-- Índices de tabela `cep`
--
ALTER TABLE `cep`
  ADD PRIMARY KEY (`ID_CEP`),
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
  ADD PRIMARY KEY (`ID_CONECTOR`);

--
-- Índices de tabela `cor`
--
ALTER TABLE `cor`
  ADD PRIMARY KEY (`ID_COR`);

--
-- Índices de tabela `estado`
--
ALTER TABLE `estado`
  ADD PRIMARY KEY (`ID_ESTADO`);

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
  ADD KEY `FK_PONTO_CARREGAMENTO_3` (`FK_STATUS_PONTO`);

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
  MODIFY `ID_BAIRRO` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cep`
--
ALTER TABLE `cep`
  MODIFY `ID_CEP` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cidade`
--
ALTER TABLE `cidade`
  MODIFY `ID_CIDADE` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `conector`
--
ALTER TABLE `conector`
  MODIFY `ID_CONECTOR` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `cor`
--
ALTER TABLE `cor`
  MODIFY `ID_COR` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `estado`
--
ALTER TABLE `estado`
  MODIFY `ID_ESTADO` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT de tabela `rota`
--
ALTER TABLE `rota`
  MODIFY `ID_ROTA` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuario`
--
ALTER TABLE `usuario`
  MODIFY `ID_USER` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  ADD CONSTRAINT `FK_PONTO_CARREGAMENTO_3` FOREIGN KEY (`FK_STATUS_PONTO`) REFERENCES `status_ponto` (`ID_STATUS_PONTO`);

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
COMMIT;

/* TESTE DE CONEXÃO */
SELECT NOW() AS data_atual;






/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
