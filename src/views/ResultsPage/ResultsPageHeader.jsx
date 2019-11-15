import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { getMainType } from '../../store/actions';
import { Link } from 'react-router-dom';
import { SearchCriteriaTable } from '../../components/atomic';
import { history } from '../../services/history.service';

const ResultsPageHeader = ({
  handleUpdate,
  handleReset,
  resultsCount,
  pageNum,
  step = 10,
}) => {
  const dispatch = useDispatch();
  const {
    formType,
    cancerType,
    age,
    zip,
    keywordPhrases,
    isDirty,
  } = useSelector(store => store.form);

  const { maintypeOptions } = useSelector(store => store.cache);

  const handleRefineSearch = () => {
    if (formType === 'basic') {
      //prefetch stuff
      if (!maintypeOptions || maintypeOptions.length < 1) {
        dispatch(getMainType({}));
      }
      if (cancerType.name !== '') {
        handleUpdate('cancerTypeModified', true);
      }
      if (age !== '') {
        handleUpdate('ageModified', true);
      }
      if (zip !== '') {
        handleUpdate('zipModified', true);
      }
      if (keywordPhrases !== '') {
        handleUpdate('keywordPhrasesModified', true);
      }
      handleUpdate('formType', 'advanced');
    }
    handleUpdate('refineSearch', true);
    history.push('/about-cancer/treatment/clinical-trials/search/advanced');
  };

  return (
    <div className="cts-results-header">
      <p>
        <strong>
          {resultsCount === 0 ? (
            <>No clinical trials matched your search.</>
          ) : (
            <>
              Results{' '}
              {`${pageNum * step + 1}-${
                resultsCount <= step * (pageNum + 1)
                  ? resultsCount
                  : step * (pageNum + 1)
              } `}{' '}
              of {resultsCount} for your search{' '}
              {`${!isDirty ? 'for: "all trials"' : ''}`}
            </>
          )}
        </strong>
        {!isDirty && (
          <>
            {' '}
            &nbsp;| &nbsp;{' '}
            <Link
              to={`/about-cancer/treatment/clinical-trials/search${
                formType === 'basic' ? '' : '/advanced'
              }`}
              onClick={handleReset}
            >
              Start Over
            </Link>
          </>
        )}
      </p>

      <SearchCriteriaTable handleUpdate={handleUpdate} />
      <p className="reset-form">
        <Link
          to={`/about-cancer/treatment/clinical-trials/search${
            formType === 'basic' ? '' : '/advanced'
          }`}
          onClick={handleReset}
        >
          Start Over
        </Link>
        {isDirty && (
          <>
            <span aria-hidden="true" className="separator">
              |
            </span>
            <button
              type="button"
              className="btnAsLink"
              onClick={handleRefineSearch}
            >
              Modify Search Criteria
            </button>
          </>
        )}
      </p>
    </div>
  );
};

export default ResultsPageHeader;
