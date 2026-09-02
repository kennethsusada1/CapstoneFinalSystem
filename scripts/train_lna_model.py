#!/usr/bin/env python3
"""Train and export the explainable LNA Logistic Regression model.

The application consumes the exported JSON artifact and does not need Python
or NumPy at request time.  The supplied dataset is synthetic, so the artifact
is intended for the capstone prototype and must be retrained with validated
organizational data before production use.
"""

from __future__ import annotations

import argparse
import csv
import json
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path

import numpy as np


NUMERIC_FEATURES = [
    "employee_assessment",
    "supervisor_assessment",
    "required_level",
    "skill_gap",
    "ipcr_rating",
    "trainings_last_3_years",
    "years_of_service",
    "seniority_level",
]
CATEGORICAL_FEATURES = [
    "role_family",
    "education_level",
    "employment_status",
    "competency_category",
]
TARGET = "training_needed"


def read_rows(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def number(value: str, fallback: float = 0.0) -> float:
    try:
        return float(value)
    except (TypeError, ValueError):
        return fallback


def sigmoid(value: np.ndarray | float) -> np.ndarray | float:
    clipped = np.clip(value, -35.0, 35.0)
    return 1.0 / (1.0 + np.exp(-clipped))


def feature_schema(rows: list[dict[str, str]]) -> dict:
    means = {
        field: float(np.mean([number(row.get(field, "")) for row in rows]))
        for field in NUMERIC_FEATURES
    }
    scales = {}
    for field in NUMERIC_FEATURES:
        values = np.array([number(row.get(field, ""), means[field]) for row in rows])
        scale = float(np.std(values))
        scales[field] = scale if scale > 1e-9 else 1.0

    categories = {
        field: sorted({row.get(field, "").strip() for row in rows if row.get(field, "").strip()})
        for field in CATEGORICAL_FEATURES
    }
    feature_names = list(NUMERIC_FEATURES)
    for field in CATEGORICAL_FEATURES:
        feature_names.extend(f"{field}={value}" for value in categories[field])

    return {
        "numeric_features": NUMERIC_FEATURES,
        "categorical_features": CATEGORICAL_FEATURES,
        "numeric_means": means,
        "numeric_scales": scales,
        "categories": categories,
        "feature_names": feature_names,
    }


def transform(rows: list[dict[str, str]], schema: dict) -> np.ndarray:
    feature_names = schema["feature_names"]
    positions = {name: index for index, name in enumerate(feature_names)}
    matrix = np.zeros((len(rows), len(feature_names)), dtype=float)

    for row_index, row in enumerate(rows):
        for field in NUMERIC_FEATURES:
            value = number(row.get(field, ""), schema["numeric_means"][field])
            matrix[row_index, positions[field]] = (
                value - schema["numeric_means"][field]
            ) / schema["numeric_scales"][field]
        for field in CATEGORICAL_FEATURES:
            value = row.get(field, "").strip()
            if value in schema["categories"][field]:
                matrix[row_index, positions[f"{field}={value}"]] = 1.0

    return matrix


def fit(matrix: np.ndarray, labels: np.ndarray) -> tuple[np.ndarray, float]:
    weights = np.zeros(matrix.shape[1], dtype=float)
    intercept = 0.0
    learning_rate = 0.08
    regularization = 0.08
    sample_count = matrix.shape[0]

    for _ in range(4000):
        probabilities = sigmoid(matrix @ weights + intercept)
        error = probabilities - labels
        gradient = (matrix.T @ error) / sample_count + regularization * weights
        intercept_gradient = float(np.mean(error))
        weights -= learning_rate * gradient
        intercept -= learning_rate * intercept_gradient

    return weights, intercept


def roc_auc(labels: np.ndarray, probabilities: np.ndarray) -> float | None:
    positives = int(np.sum(labels == 1))
    negatives = int(np.sum(labels == 0))
    if positives == 0 or negatives == 0:
        return None

    order = np.argsort(probabilities)
    ranks = np.empty_like(order, dtype=float)
    ranks[order] = np.arange(1, len(probabilities) + 1)
    positive_rank_sum = float(np.sum(ranks[labels == 1]))
    return (positive_rank_sum - positives * (positives + 1) / 2) / (positives * negatives)


def metrics(labels: np.ndarray, probabilities: np.ndarray, threshold: float = 0.5) -> dict:
    predictions = (probabilities >= threshold).astype(int)
    true_positive = int(np.sum((predictions == 1) & (labels == 1)))
    true_negative = int(np.sum((predictions == 0) & (labels == 0)))
    false_positive = int(np.sum((predictions == 1) & (labels == 0)))
    false_negative = int(np.sum((predictions == 0) & (labels == 1)))
    precision = true_positive / (true_positive + false_positive) if true_positive + false_positive else 0.0
    recall = true_positive / (true_positive + false_negative) if true_positive + false_negative else 0.0
    f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
    accuracy = (true_positive + true_negative) / len(labels)
    clipped = np.clip(probabilities, 1e-7, 1 - 1e-7)
    log_loss = float(-np.mean(labels * np.log(clipped) + (1 - labels) * np.log(1 - clipped)))

    return {
        "rows": int(len(labels)),
        "positive_rate": round(float(np.mean(labels)), 4),
        "accuracy": round(accuracy, 4),
        "precision": round(precision, 4),
        "recall": round(recall, 4),
        "f1": round(f1, 4),
        "roc_auc": None if roc_auc(labels, probabilities) is None else round(roc_auc(labels, probabilities), 4),
        "log_loss": round(log_loss, 4),
    }


def recommendation_catalog(rows: list[dict[str, str]]) -> dict[str, dict]:
    grouped: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in rows:
        competency = row.get("competency_name", "").strip()
        training = row.get("recommended_training", "").strip()
        if competency and training and training != "No immediate training required":
            grouped[competency.casefold()].append(row)

    catalog = {}
    for competency_key, items in grouped.items():
        names = Counter(item["recommended_training"] for item in items)
        first = items[0]
        catalog[competency_key] = {
            "competency_name": first["competency_name"],
            "competency_category": first.get("competency_category", ""),
            "required_level": round(float(np.mean([number(item.get("required_level")) for item in items])), 2),
            "training_title": names.most_common(1)[0][0],
        }
    return catalog


def train_model(rows: list[dict[str, str]], threshold: float) -> tuple[dict, dict]:
    schema = feature_schema(rows)
    matrix = transform(rows, schema)
    labels = np.array([int(row[TARGET]) for row in rows], dtype=int)
    weights, intercept = fit(matrix, labels)
    return {
        **schema,
        "weights": [round(float(value), 10) for value in weights],
        "intercept": round(float(intercept), 10),
        "threshold": threshold,
    }, metrics(labels, sigmoid(matrix @ weights + intercept), threshold)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--input", required=True, type=Path, help="Competency-level CSV dataset")
    parser.add_argument("--output", required=True, type=Path, help="Exported model JSON path")
    parser.add_argument("--threshold", type=float, default=0.5)
    parser.add_argument("--model-version", default="lna-logistic-v1")
    parser.add_argument("--data-source", default="synthetic")
    args = parser.parse_args()

    rows = read_rows(args.input)
    years = sorted({int(row["year"]) for row in rows})
    latest_year = years[-1]
    train_rows = [row for row in rows if int(row["year"]) < latest_year]
    test_rows = [row for row in rows if int(row["year"]) == latest_year]

    validation_model, validation_metrics = train_model(train_rows, args.threshold)
    final_model, training_metrics = train_model(rows, args.threshold)
    artifact = {
        "model_version": args.model_version,
        "algorithm": "binary logistic regression with L2 regularization",
        "target": TARGET,
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "dataset": {
            "path": args.input.name,
            "rows": len(rows),
            "years": years,
            "training_rows": len(train_rows),
            "validation_rows": len(test_rows),
            "synthetic": args.data_source == "synthetic",
            "source": args.data_source,
        },
        "validation": {
            "train_years": years[:-1],
            "test_years": [latest_year],
            "metrics": metrics(
                np.array([int(row[TARGET]) for row in test_rows], dtype=int),
                sigmoid(
                    transform(test_rows, validation_model) @ np.array(validation_model["weights"])
                    + validation_model["intercept"]
                ),
                args.threshold,
            ),
        },
        "training_metrics": training_metrics,
        "feature_schema": final_model,
        "recommendation_catalog": recommendation_catalog(rows),
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(artifact, indent=2), encoding="utf-8")
    print(json.dumps({"output": str(args.output), "validation": artifact["validation"], "training": training_metrics}, indent=2))


if __name__ == "__main__":
    main()
