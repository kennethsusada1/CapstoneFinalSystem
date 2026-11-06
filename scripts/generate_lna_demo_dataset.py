#!/usr/bin/env python3
"""Generate a reproducible, synthetic LNA dataset for local demonstrations.

The PDF describes 840 LNA records from 168 employees over five assessment
years. This generator creates that record profile and expands each assessment
into competency-level rows for the logistic-regression training script. These
records are demonstration data and must not be presented as actual employee
history.
"""

from __future__ import annotations

import argparse
import csv
import random
from pathlib import Path


YEARS = range(2022, 2027)
EMPLOYEE_COUNT = 168

COMPETENCIES = [
    ("Communication Skills", "Functional Competencies", 3, "Technical Writing and Presentation Skills Training"),
    ("Public Speaking", "Functional Competencies", 3, "Technical Writing and Presentation Skills Training"),
    ("Negotiation Skills", "Functional Competencies", 3, "Communication and Negotiation Workshop"),
    ("Presentation Skills", "Functional Competencies", 3, "Technical Writing and Presentation Skills Training"),
    ("Technical Writing", "Functional Competencies", 3, "Technical Writing and Presentation Skills Training"),
    ("Active Listening", "Functional Competencies", 3, "Communication and Negotiation Workshop"),
    ("Teamwork", "Interpersonal Competencies", 3, "Teamwork and Collaboration Workshop"),
    ("Behavioral Management", "Interpersonal Competencies", 3, "Behavioral Management Workshop"),
    ("Client-relationship Management", "Interpersonal Competencies", 3, "Customer Service Excellence Program"),
    ("Integrity and Honesty", "Ethical and Professional Competencies", 3, "Public Service Ethics and Compliance Workshop"),
    ("Adherence to legal and regulatory requirements", "Ethical and Professional Competencies", 3, "Public Service Ethics and Compliance Workshop"),
    ("Professionalism", "Ethical and Professional Competencies", 3, "Public Service Ethics and Compliance Workshop"),
    ("Team Management", "Leadership Competencies", 3, "Supervisory Development Program"),
    ("Conflict Resolution", "Leadership Competencies", 3, "Supervisory Development Program"),
    ("Delegation", "Leadership Competencies", 3, "Supervisory Development Program"),
    ("Motivation and Coaching", "Leadership Competencies", 3, "Supervisory Development Program"),
    ("Flexibility", "Adaptability Competencies", 3, "Workplace Adaptability and Resilience Workshop"),
    ("Resilience", "Adaptability Competencies", 3, "Workplace Adaptability and Resilience Workshop"),
    ("Stress Management", "Adaptability Competencies", 3, "Workplace Adaptability and Resilience Workshop"),
    ("Analytical Thinking", "Problem Solving and Decision Making", 3, "Data Analysis and Problem Solving Workshop"),
    ("Creative Thinking", "Problem Solving and Decision Making", 3, "Data Analysis and Problem Solving Workshop"),
    ("Critical Thinking", "Problem Solving and Decision Making", 3, "Data Analysis and Problem Solving Workshop"),
    ("Troubleshooting", "Problem Solving and Decision Making", 3, "Data Analysis and Problem Solving Workshop"),
    ("Innovation", "Problem Solving and Decision Making", 3, "Data Analysis and Problem Solving Workshop"),
    ("Time Management", "Organizational Competencies", 3, "Project Planning and Monitoring Workshop"),
    ("Multitasking", "Organizational Competencies", 3, "Project Planning and Monitoring Workshop"),
    ("Compliance and Risk Awareness", "Organizational Competencies", 3, "Public Service Ethics and Compliance Workshop"),
    ("Resource Management", "Organizational Competencies", 3, "Project Planning and Monitoring Workshop"),
    ("Strategic Planning", "Organizational Competencies", 4, "Project Planning and Monitoring Workshop"),
    ("Project Planning and Scheduling", "Organizational Competencies", 3, "Project Planning and Monitoring Workshop"),
]

ROLE_FAMILIES = ["administration", "finance", "health", "hr", "ict", "planning"]
EDUCATION_LEVELS = [
    "High School",
    "Vocational Certificate",
    "Associate Degree",
    "Bachelor's Degree",
    "Master's Degree",
    "Doctoral Degree",
]
CSV_COLUMNS = [
    "competency_name",
    "recommended_training",
    "employee_assessment",
    "required_level",
    "ipcr_rating",
    "trainings_last_3_years",
    "years_of_service",
    "seniority_level",
    "role_family",
    "education_level",
    "employment_status",
    "competency_category",
    "year",
    "training_needed",
]


def clamp(value: float, lower: float, upper: float) -> float:
    return max(lower, min(upper, value))


def rounded_rating(value: float) -> int:
    return int(clamp(value + 0.5, 1, 4))


def generate_rows(seed: int) -> list[dict[str, int | float | str]]:
    randomizer = random.Random(seed)
    rows: list[dict[str, int | float | str]] = []

    for employee_number in range(1, EMPLOYEE_COUNT + 1):
        employee_skill = randomizer.gauss(0, 0.65)
        role_family = ROLE_FAMILIES[(employee_number - 1) % len(ROLE_FAMILIES)]
        education_level = randomizer.choice(EDUCATION_LEVELS)
        employment_status = "Permanent"
        seniority_level = randomizer.choices([1, 2, 3, 4], weights=[2, 4, 3, 1], k=1)[0]
        hire_year = randomizer.randint(1998, 2021)

        for year in YEARS:
            years_of_service = year - hire_year
            trainings_last_3_years = int(clamp(round(2.4 + employee_skill * 1.1 + randomizer.gauss(0, 1.25)), 0, 8))
            ipcr_rating = round(clamp(3.05 + employee_skill * 0.3 + (year - 2022) * 0.025 + randomizer.gauss(0, 0.28), 1, 4), 2)

            for competency_index, (competency, category, required_level, training) in enumerate(COMPETENCIES):
                competency_effect = ((competency_index % 7) - 3) * 0.08
                assessment = rounded_rating(
                    2.65
                    + employee_skill
                    + competency_effect
                    + (year - 2022) * 0.12
                    + trainings_last_3_years * 0.035
                    + randomizer.gauss(0, 0.62)
                )
                supervisor_assessment = int(clamp(assessment + randomizer.choices([-1, 0, 1], weights=[2, 6, 2], k=1)[0], 1, 4))

                rows.append({
                    "competency_name": competency,
                    "recommended_training": training,
                    "employee_assessment": assessment,
                    "required_level": required_level,
                    "ipcr_rating": ipcr_rating,
                    "trainings_last_3_years": trainings_last_3_years,
                    "years_of_service": years_of_service,
                    "seniority_level": seniority_level,
                    "role_family": role_family,
                    "education_level": education_level,
                    "employment_status": employment_status,
                    "competency_category": category,
                    "year": year,
                    # As in the application training pipeline, the supervisor's
                    # assessment supplies the label and is excluded as a feature.
                    "training_needed": int(supervisor_assessment <= 2),
                })

    return rows


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", required=True, type=Path, help="Output CSV path for the synthetic competency rows")
    parser.add_argument("--seed", type=int, default=20261106)
    args = parser.parse_args()

    rows = generate_rows(args.seed)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=CSV_COLUMNS)
        writer.writeheader()
        writer.writerows(rows)

    print(f"Generated {EMPLOYEE_COUNT * len(YEARS)} synthetic LNA records as {len(rows)} competency rows at {args.output}.")


if __name__ == "__main__":
    main()
