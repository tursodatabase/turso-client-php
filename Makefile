.PHONY: help compose/up compose/down compose/restart compose/logs compose-arm64/up compose-arm64/down compose-arm64/restart compose-arm64/logs

help:
	@echo "Usage:"
	@echo " make help"
	@echo " make compose/up"
	@echo " make compose/down"
	@echo " make compose/restart"
	@echo " make compose/logs"
	@echo " make compose-arm64/up"
	@echo " make compose-arm64/down"
	@echo " make compose-arm64/restart"
	@echo " make compose-arm64/logs"

compose/up:
	docker compose --profile=dev up -d
compose/down:
	docker compose --profile=dev down
compose/restart:
	docker compose --profile=dev restart
compose/logs:
	docker compose --profile=dev logs
compose-arm64/up:
	docker compose --profile=arm64 up -d
compose-arm64/down:
	docker compose --profile=arm64 down
compose-arm64/restart:
	docker compose --profile=arm64 restart
compose-arm64/logs:
	docker compose --profile=arm64 logs